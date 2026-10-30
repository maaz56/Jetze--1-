<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingRequest;
use App\Models\FlightBookings;
use App\Models\FlightPassenger;
use App\Models\User;
use App\Services\Booking\BookingRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Void / refund requests: customers raise them for their own bookings,
 * admins (or staff with manage-bookings) approve or reject them.
 */
class BookingRequestController extends Controller
{
    private const STATUSES = ['pending', 'processing', 'approved', 'rejected', 'failed', 'cancelled'];

    public function __construct(
        protected BookingRequestService $bookingRequestService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:all,' . implode(',', self::STATUSES),
            'type' => 'nullable|string|in:all,void,refund',
            'provider' => 'nullable|string|max:50',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = $this->scopedQuery($request->user())->with(BookingRequestService::RELATIONS);

        if (($validated['status'] ?? 'all') !== 'all') {
            $query->where('status', $validated['status']);
        }

        if (($validated['type'] ?? 'all') !== 'all') {
            $query->where('type', $validated['type']);
        }

        if (($validated['provider'] ?? 'all') !== 'all') {
            $query->whereHas('booking', fn ($b) => $b->where('flight_provider', $validated['provider']));
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $isManager = $this->canManage($request->user());

            $query->where(function ($q) use ($search, $isManager) {
                $q->whereHas('booking', function ($b) use ($search) {
                    $b->where('pnr', 'like', "%{$search}%")
                        ->orWhere('itinerary_ref', 'like', "%{$search}%");
                })->orWhereHas('passengers', function ($p) use ($search) {
                    $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });

                if ($isManager) {
                    $q->orWhereHas('requester', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($validated['per_page'] ?? 15),
        ]);
    }

    public function counts(Request $request): JsonResponse
    {
        $user = $request->user();
        $byStatus = $this->scopedQuery($user)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = ['all' => (int) $byStatus->sum()];
        foreach (self::STATUSES as $status) {
            $counts[$status] = (int) ($byStatus[$status] ?? 0);
        }

        if ($this->canManage($user)) {
            $counts['providers'] = FlightBookings::whereIn('id', BookingRequest::select('booking_id'))
                ->whereNotNull('flight_provider')
                ->distinct()
                ->orderBy('flight_provider')
                ->pluck('flight_provider');
        }

        return response()->json($counts);
    }

    /**
     * Find one of the user's own bookings by PNR for the new-request form.
     * Only the fields the form needs are returned.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate(['pnr' => 'required|string|max:100']);
        $booking = $this->findBookingForUser($request->user(), trim($validated['pnr']));

        if (!$booking) {
            return response()->json(['message' => 'No booking found for this PNR.'], 404);
        }

        $passengers = FlightPassenger::where('booking_id', $booking->id)->get();
        [$currency, $amounts] = $this->bookingRequestService->passengerAmounts($booking, $passengers);

        return response()->json([
            'booking' => $booking->only(['id', 'pnr', 'itinerary_ref', 'flight_provider', 'status']),
            'currency' => $currency,
            'passengers' => $passengers->map(fn ($pax) => [
                'id' => $pax->id,
                'title' => $pax->title,
                'first_name' => $pax->first_name,
                'last_name' => $pax->last_name,
                'type' => $pax->type,
                'status' => $pax->status,
                'amount' => $amounts[$pax->id] ?? 0,
            ])->values(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $bookingRequest = $this->scopedQuery($request->user())
            ->with(BookingRequestService::RELATIONS)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $bookingRequest]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required_without:pnr|nullable|integer',
            'pnr' => 'required_without:booking_id|nullable|string|max:100',
            'type' => 'required|in:void,refund',
            'passenger_ids' => 'required|array|min:1',
            'passenger_ids.*' => 'integer|distinct',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $booking = !empty($validated['booking_id'])
            ? $this->bookingsForUser($user)->whereKey($validated['booking_id'])->first()
            : $this->findBookingForUser($user, trim($validated['pnr']));

        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        $bookingRequest = $this->bookingRequestService->submit(
            booking: $booking,
            user: $user,
            type: $validated['type'],
            passengerIds: $validated['passenger_ids'],
            remarks: isset($validated['remarks']) ? trim($validated['remarks']) : null,
        );

        return response()->json([
            'message' => ucfirst($bookingRequest->type) . ' request submitted successfully.',
            'data' => $bookingRequest,
        ], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $this->ensureManager($request->user());

        $validated = $request->validate([
            'penalty_amount' => 'nullable|numeric|min:0|max:99999999',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $updated = $this->bookingRequestService->approve(
            request: BookingRequest::findOrFail($id),
            admin: $request->user(),
            penalty: (float) ($validated['penalty_amount'] ?? 0),
            remarks: $validated['remarks'] ?? null,
        );

        return response()->json(['message' => 'Request approved successfully.', 'data' => $updated]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $this->ensureManager($request->user());

        $validated = $request->validate([
            'remarks' => 'required|string|max:1000',
        ]);

        $updated = $this->bookingRequestService->reject(
            request: BookingRequest::findOrFail($id),
            admin: $request->user(),
            remarks: trim($validated['remarks']),
        );

        return response()->json(['message' => 'Request rejected successfully.', 'data' => $updated]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $updated = $this->bookingRequestService->cancel(
            $this->scopedQuery($request->user())->findOrFail($id),
            $request->user()
        );

        return response()->json(['message' => 'Request cancelled successfully.', 'data' => $updated]);
    }

    private function canManage(?User $user): bool
    {
        return (bool) $user && ($user->role === 'admin' || $user->can('manage-bookings'));
    }

    private function ensureManager(?User $user): void
    {
        abort_unless($this->canManage($user), 403, 'Only administrators can review cancellation requests.');
    }

    /** Managers see every request; everyone else only sees requests on their own bookings. */
    private function scopedQuery(User $user): Builder
    {
        $query = BookingRequest::query();

        if (!$this->canManage($user)) {
            $query->where(function ($q) use ($user) {
                $q->where('requested_by', $user->id)
                    ->orWhereHas('booking', fn ($b) => $b->where('agent_id', $user->id));
            });
        }

        return $query;
    }

    private function bookingsForUser(User $user): Builder
    {
        $query = FlightBookings::query();

        if (!$this->canManage($user)) {
            $query->where('agent_id', $user->id);
        }

        return $query;
    }

    private function findBookingForUser(User $user, string $pnr): ?FlightBookings
    {
        return $this->bookingsForUser($user)
            ->where(fn ($q) => $q->where('pnr', $pnr)->orWhere('itinerary_ref', $pnr))
            ->latest('id')
            ->first();
    }
}
