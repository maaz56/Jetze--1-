<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ModifyRequestMessageSent;
use App\Events\UserNotificationCreated;
use App\Models\FlightBookings;
use App\Models\ModifyRequest;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Log;

class ModifyRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|integer|exists:flight_bookings,id',
            'reason' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();
        $initialMessage = trim((string) ($validated['message'] ?? $validated['description'] ?? '')) ?: null;

        $modifyRequest = DB::transaction(function () use ($validated, $user, $initialMessage) {
            $booking = FlightBookings::lockForUpdate()->findOrFail($validated['booking_id']);
            $this->ensureBookingOwnerOrManager($booking, $user);

            if (in_array(strtolower((string) $booking->status), ['canceled', 'cancelled', 'voided', 'refunded', 'expired', 'failed'], true)) {
                abort(422, 'A modification request cannot be submitted for a closed booking.');
            }

            $hasPendingRequest = ModifyRequest::query()
                ->where('booking_id', $booking->id)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists();
            if ($hasPendingRequest) {
                abort(422, 'A modification request is already pending for this booking.');
            }

            $modifyRequest = new ModifyRequest();
            $modifyRequest->booking_id = $booking->id;
            $modifyRequest->user_id = $user->id;
            $modifyRequest->status = 'pending';
            $modifyRequest->reason = $validated['reason'] ?? null;
            $modifyRequest->message = $initialMessage;
            $modifyRequest->messages = $initialMessage
                ? json_encode([[
                    'sender' => 'user',
                    'name' => $user->name,
                    'sender_id' => $user->id,
                    'message' => $initialMessage,
                    'timestamp' => now(),
                ]])
                : '[]';
            $modifyRequest->save();

            return $modifyRequest;
        });
        $modifyRequest->load('booking', 'user');
        $notification = [
            'type' => 'modify_request_submitted',
            'heading' => 'New Modify Request',
            'description' => "{$modifyRequest->user?->name} submitted a booking modification request.",
            'booking_id' => $modifyRequest->booking_id,
            'modify_request_id' => $modifyRequest->id,
            'url' => $this->modifyRequestChatUrl($modifyRequest),
        ];
        User::query()->where('role', 'admin')->each(
            fn (User $admin) => $this->notifyUser($admin, $notification)
        );
        
        return response()->json($modifyRequest, 201);
    }


    public function addMessage(Request $request)
    {
        $validated = $request->validate([
            'req_id' => 'required|integer',
            'message' => 'required|string|max:2000',
        ]);

        $sender = $request->user();
        $modify = ModifyRequest::with(['booking', 'user'])->findOrFail($validated['req_id']);
        $isRequestOwner = (int) $modify->user_id === (int) $sender->id;
        $canManageBookings = $sender->role === 'admin' || $sender->can('manage-bookings');
        abort_unless($isRequestOwner || $canManageBookings, 403, 'You cannot reply to this request.');

        $sentAt = now()->toISOString();
        $newMsg = DB::transaction(function () use ($modify, $sender, $canManageBookings, $validated, $sentAt) {
            $lockedRequest = ModifyRequest::lockForUpdate()->findOrFail($modify->id);
            $messages = json_decode($lockedRequest->messages ?: '[]', true) ?: [];
            $message = [
                'sender' => $canManageBookings ? 'admin' : 'user',
                'sender_id' => $sender->id,
                'name' => $sender->name,
                'message' => trim($validated['message']),
                'timestamp' => $sentAt,
                'created_at' => $sentAt,
            ];

            $messages[] = $message;
            $lockedRequest->messages = json_encode($messages);
            $lockedRequest->save();

            return $message;
        });

        event(new ModifyRequestMessageSent($modify->id, $newMsg));

        $notification = [
            'type' => 'modify_request_message',
            'heading' => 'New message on modify request',
            'description' => "{$sender->name} sent a message about a booking modification.",
            'booking_id' => $modify->booking_id,
            'modify_request_id' => $modify->id,
            'url' => $this->modifyRequestChatUrl($modify),
        ];

        if ($canManageBookings) {
            if ($modify->user) {
                $this->notifyUser($modify->user, $notification);
            }
        } else {
            User::query()->where('role', 'admin')->each(
                fn (User $admin) => $this->notifyUser($admin, $notification)
            );
        }

        return response()->json([
            'status' => true,
            'message' => $newMsg,
        ]);
    }

    public function openChat(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|integer',
        ]);

        $user = $request->user();
        [$modifyRequest, $wasCreated] = DB::transaction(function () use ($validated, $user) {
            $booking = FlightBookings::lockForUpdate()->findOrFail($validated['booking_id']);
            $this->ensureBookingOwnerOrManager($booking, $user);

            if (in_array(strtolower((string) $booking->status), ['canceled', 'cancelled', 'voided', 'refunded', 'expired', 'failed'], true)) {
                abort(422, 'A modification request cannot be opened for a closed booking.');
            }

            $modifyRequest = ModifyRequest::query()
                ->where('booking_id', $booking->id)
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();

            if ($modifyRequest) {
                return [$modifyRequest, false];
            }

            $modifyRequest = ModifyRequest::create([
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'reason' => 'booking_chat',
                'messages' => '[]',
            ]);

            return [$modifyRequest, true];
        });

        if ($wasCreated) {
            $modifyRequest->load(['booking', 'user']);

            $notification = [
                'type' => 'modify_request_submitted',
                'heading' => 'New booking chat',
                'description' => "{$user->name} started a chat about a booking.",
                'booking_id' => $modifyRequest->booking_id,
                'modify_request_id' => $modifyRequest->id,
                'url' => $this->modifyRequestChatUrl($modifyRequest),
            ];
            User::query()->where('role', 'admin')->each(
                fn (User $admin) => $this->notifyUser($admin, $notification)
            );
        }

        return response()->json(['data' => $modifyRequest->loadMissing(['booking', 'user'])]);
    }

    private function notifyUser(User $user, array $payload): void
    {
        $notification = new SystemNotification($payload);
        $user->notify($notification);

        event(new UserNotificationCreated($user->id, $payload + [
            'id' => $notification->id,
            'type' => SystemNotification::class,
            'read_at' => null,
            'created_at' => now()->toISOString(),
            'notification_type' => $payload['type'] ?? null,
        ]));
    }

    private function modifyRequestChatUrl(ModifyRequest $modifyRequest): string
    {
        $booking = $modifyRequest->booking;
        if (!$booking) {
            return '/admin/booking-requests?'.http_build_query([
                'inbox' => 'modify',
                'modify_request_id' => $modifyRequest->id,
            ]);
        }

        return '/customer-bookings-details?'.http_build_query(array_filter([
            'booking_id' => $booking->id,
            'pnr' => $booking->itinerary_ref ?: $booking->pnr,
            'agent_id' => $booking->agent_id,
            'booking_source' => $booking->booking_source,
            'flight_provider' => $booking->flight_provider,
            'flight_mode' => $booking->booking_mode ?: 'B2C',
            'modify_request_id' => $modifyRequest->id,
            'chat' => 1,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $requests = ModifyRequest::with(['booking', 'user'])
            ->when(! $this->canManageBookings($user), fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function show(Request $request)
    {
        Log::info('ModifyRequestController@show called with data: ', $request->all());
        $validated = $request->validate([
            'modify_request_id' => 'required|integer',
        ]);

    }

    public function fetchModifyRequestData(Request $request)
    {
        $validated = $request->validate([
            'modify_request_id' => 'nullable|integer',
            'booking_id' => 'nullable|integer',
        ]);

        $query = ModifyRequest::with(['booking', 'user']);
        $user = $request->user();
        if (! $this->canManageBookings($user)) {
            $query->where('user_id', $user->id);
        }

        // Priority 1: modify_request_id
        if (!empty($validated['modify_request_id'])) {
            $query->where('id', $validated['modify_request_id']);
        }
        // Priority 2: booking_id
        elseif (!empty($validated['booking_id'])) {
            $query->where('booking_id', $validated['booking_id']);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either modify_request_id or booking_id is required.'
            ], 400);
        }

        $modifyRequest = $query->first();

        if (!$modifyRequest) {
            Log::warning("ModifyRequest not found for parameters:", $validated);
            return response()->json([
                'success' => false,
                'message' => 'Modify request not found'
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => $modifyRequest
        ]);
    }

    public  function updateStatus(Request $request)
    {
        abort_unless($this->canManageBookings($request->user()), 403, 'Only administrators can review modification requests.');

        $validated = $request->validate([
            'modify_request_id' => 'required|integer',
            'status' => 'required|string|in:approved,rejected',
        ]);

        $modifyRequest = ModifyRequest::find($validated['modify_request_id']);
        if (!$modifyRequest) {
            Log::warning("ModifyRequest not found for ID: " . $validated['modify_request_id']);
            return response()->json([
                'success' => false,
                'message' => 'Modify request not found'
            ], 404);
        }

        if ($modifyRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "This request cannot be {$validated['status']} from its current status.",
            ], 422);
        }

        DB::transaction(function () use ($modifyRequest, $validated, $request) {
            $lockedRequest = ModifyRequest::lockForUpdate()->findOrFail($modifyRequest->id);
            if ($lockedRequest->status !== 'pending') {
                abort(422, "This request cannot be {$validated['status']} from its current status.");
            }

            $lockedRequest->status = $validated['status'];
            $lockedRequest->reviewed_by = $request->user()->id;
            $lockedRequest->reviewed_at = now();
            $lockedRequest->save();
        });
        $modifyRequest->refresh()->load('reviewer:id,name');
        $notification = [
            'type' => 'modify_request_'.$modifyRequest->status,
            'heading' => 'Modify Request '.ucfirst($modifyRequest->status),
            'description' => "Your booking modification request has been {$modifyRequest->status}.",
            'booking_id' => $modifyRequest->booking_id,
            'modify_request_id' => $modifyRequest->id,
            'url' => $this->modifyRequestChatUrl($modifyRequest),
        ];
        if ($modifyRequest->user) {
            $this->notifyUser($modifyRequest->user, $notification);
        }

        return response()->json([
            'success' => true,
            'data' => $modifyRequest
        ]);
    }

    private function canManageBookings(User $user): bool
    {
        return $user->role === 'admin' || $user->can('manage-bookings');
    }

    private function ensureBookingOwnerOrManager(FlightBookings $booking, User $user): void
    {
        abort_unless(
            $this->canManageBookings($user) || (int) $booking->agent_id === (int) $user->id,
            403,
            'You cannot create a modification request for this booking.'
        );
    }

}
