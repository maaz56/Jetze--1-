<?php

namespace App\Services\Booking;

use App\Mail\BookingRequestDecisionMail;
use App\Mail\BookingRequestSubmittedMail;
use App\Models\AgentCharge;
use App\Models\BookingRequest;
use App\Models\FlightBookings;
use App\Models\FlightPassenger;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Customer void / refund requests. Approval updates statuses in the database and,
 * for wallet-paid bookings, records the credit and penalty in the wallet ledger.
 * No provider endpoint is called.
 */
class BookingRequestService
{
    /** Booking statuses that can no longer be voided or refunded. */
    public const CLOSED_BOOKING_STATUSES = ['voided', 'refunded', 'canceled', 'cancelled', 'rejected', 'expired', 'failed'];

    public const RELATIONS = [
        'booking:id,pnr,itinerary_ref,flight_provider,status,agent_id,booking_source,booking_mode',
        'requester:id,name,email',
        'reviewer:id,name',
        'passengers:id,booking_id,title,first_name,last_name,type,status',
    ];

    public function submit(
        FlightBookings $booking,
        User $user,
        string $type,
        array $passengerIds,
        ?string $remarks = null
    ): BookingRequest {
        if (!in_array($type, BookingRequest::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Request type must be void or refund.']);
        }

        $bookingRequest = DB::transaction(function () use ($booking, $user, $type, $passengerIds, $remarks) {
            // Lock the booking so two submissions for it cannot both pass the checks below.
            $lockedBooking = FlightBookings::with('priceSnapshot')->lockForUpdate()->findOrFail($booking->id);

            $currentStatus = strtolower(trim((string) $lockedBooking->status));
            if (in_array($currentStatus, self::CLOSED_BOOKING_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'booking' => "A {$type} request cannot be submitted because this booking is {$currentStatus}.",
                ]);
            }

            $hasOpenRequest = BookingRequest::where('booking_id', $lockedBooking->id)
                ->whereIn('status', BookingRequest::OPEN_STATUSES)
                ->exists();
            if ($hasOpenRequest) {
                throw ValidationException::withMessages([
                    'booking' => 'A void or refund request is already open for this booking.',
                ]);
            }

            $allPassengers = FlightPassenger::where('booking_id', $lockedBooking->id)->get();
            $selectedPassengers = $allPassengers->whereIn('id', array_map('intval', $passengerIds));

            if ($selectedPassengers->isEmpty() || $selectedPassengers->count() !== count(array_unique($passengerIds))) {
                throw ValidationException::withMessages([
                    'passenger_ids' => 'Select passengers that belong to this booking.',
                ]);
            }

            if ($selectedPassengers->contains(fn ($pax) => in_array($pax->status, FlightBookings::SETTLED_PASSENGER_STATUSES, true))) {
                throw ValidationException::withMessages([
                    'passenger_ids' => 'One or more selected passengers are already voided or refunded.',
                ]);
            }

            [$currency, $paxAmounts] = $this->passengerAmounts($lockedBooking, $allPassengers);

            $bookingRequest = BookingRequest::create([
                'booking_id' => $lockedBooking->id,
                'type' => $type,
                'status' => 'pending',
                'requested_by' => $user->id,
                'customer_remarks' => $remarks,
                'booking_status_before' => $lockedBooking->status,
                'currency' => $currency,
                'ticket_amount' => round($selectedPassengers->sum(fn ($pax) => $paxAmounts[$pax->id]), 2),
            ]);

            $bookingRequest->passengers()->sync(
                $selectedPassengers->mapWithKeys(fn ($pax) => [$pax->id => ['amount' => $paxAmounts[$pax->id]]])->all()
            );

            return $bookingRequest;
        });

        $bookingRequest->load(self::RELATIONS);
        $this->notifyAdmin($bookingRequest);

        return $bookingRequest;
    }

    public function approve(BookingRequest $request, User $admin, float $penalty = 0.0, ?string $remarks = null): BookingRequest
    {
        $approved = DB::transaction(function () use ($request, $admin, $penalty, $remarks) {
            $locked = BookingRequest::lockForUpdate()->findOrFail($request->id);
            $this->ensureReviewable($locked, 'approved');

            $ticketAmount = (float) $locked->ticket_amount;
            $penaltyAmount = round(max(0.0, $penalty), 2);
            if ($penaltyAmount > $ticketAmount) {
                throw ValidationException::withMessages([
                    'penalty_amount' => 'Penalty cannot be more than the ticket amount.',
                ]);
            }

            $booking = FlightBookings::with('priceSnapshot')->lockForUpdate()->findOrFail($locked->booking_id);
            $requestedPaxIds = $locked->passengers()->pluck('flight_passengers.id')->all();

            $alreadySettled = FlightPassenger::whereIn('id', $requestedPaxIds)
                ->whereIn('status', FlightBookings::SETTLED_PASSENGER_STATUSES)
                ->exists();
            if ($alreadySettled) {
                throw ValidationException::withMessages([
                    'status' => 'One or more passengers on this request are already voided or refunded.',
                ]);
            }

            $targetStatus = $locked->type === 'refund' ? 'refunded' : 'voided';
            FlightPassenger::whereIn('id', $requestedPaxIds)->update(['status' => $targetStatus]);

            $remaining = FlightPassenger::where('booking_id', $booking->id)
                ->whereNotIn('status', FlightBookings::SETTLED_PASSENGER_STATUSES)
                ->count();

            $booking->status = $remaining === 0 ? $targetStatus : 'partially_' . $targetStatus;
            // Passenger statuses were set above; skip the whole-booking sync hook.
            $booking->saveQuietly();

            // Like Dreamscape: the original booking debit stays in the wallet ledger, so
            // credit the requested ticket amount back and debit the penalty separately
            // (net = penalty). Bookings paid outside the wallet get no wallet entries.
            if ($booking->isChargedToWallet() && $booking->agent_id) {
                $this->recordWalletEntries($locked, $booking, $admin, $ticketAmount, $penaltyAmount, $remarks);
            }

            $locked->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_remarks' => $remarks,
                'penalty_amount' => $penaltyAmount,
                'refund_amount' => round($ticketAmount - $penaltyAmount, 2),
                'failure_reason' => null,
            ]);

            return $locked;
        });

        $approved->load(self::RELATIONS);
        $this->notifyRequester($approved);

        return $approved;
    }

    public function reject(BookingRequest $request, User $admin, string $remarks): BookingRequest
    {
        $rejected = DB::transaction(function () use ($request, $admin, $remarks) {
            $locked = BookingRequest::lockForUpdate()->findOrFail($request->id);
            $this->ensureReviewable($locked, 'rejected');

            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_remarks' => $remarks,
            ]);

            return $locked;
        });

        $rejected->load(self::RELATIONS);
        $this->notifyRequester($rejected);

        return $rejected;
    }

    public function cancel(BookingRequest $request, User $user): BookingRequest
    {
        $cancelled = DB::transaction(function () use ($request, $user) {
            $locked = BookingRequest::lockForUpdate()->findOrFail($request->id);

            if ((int) $locked->requested_by !== (int) $user->id) {
                abort(403, 'You can only cancel your own requests.');
            }

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending requests can be cancelled.']);
            }

            $locked->update(['status' => 'cancelled']);

            return $locked;
        });

        return $cancelled->load(self::RELATIONS);
    }

    /**
     * Wallet amounts are AED, while request amounts are in the customer's selling
     * currency, so convert with the same ratio the booking was debited at.
     */
    private function recordWalletEntries(
        BookingRequest $request,
        FlightBookings $booking,
        User $admin,
        float $ticketAmount,
        float $penaltyAmount,
        ?string $remarks
    ): void {
        $walletDebit = (float) ($booking->aed_amount ?? $booking->amount ?? 0);
        $sellingTotal = (float) ($booking->priceSnapshot?->selling_amount ?? $booking->selling_amount ?? $booking->amount ?? 0);
        $toWallet = $sellingTotal > 0 ? $walletDebit / $sellingTotal : 1.0;

        $pnr = $booking->itinerary_ref ?? $booking->pnr ?? $booking->id;
        $details = ucfirst($request->type) . " for PNR: {$pnr} (request #{$request->id})" . ($remarks ? " - {$remarks}" : '');
        $entry = [
            'date' => now()->toDateString(),
            'user_id' => $booking->agent_id,
            'charged_by' => $admin->id,
            'is_approved' => 1,
        ];

        AgentCharge::create($entry + [
            'amount' => round($ticketAmount * $toWallet, 2),
            'payment_type' => $request->type, // void / refund count as wallet credits
            'additional_details' => "Cancellation Credit: {$details}",
        ]);

        if ($penaltyAmount > 0) {
            AgentCharge::create($entry + [
                'amount' => round($penaltyAmount * $toWallet, 2),
                'payment_type' => 'charge',
                'additional_details' => "Cancellation Penalty: {$details}",
            ]);
        }
    }

    private function ensureReviewable(BookingRequest $request, string $action): void
    {
        if (!in_array($request->status, ['pending', 'failed'], true)) {
            throw ValidationException::withMessages([
                'status' => "Request cannot be {$action} from its current status: {$request->status}.",
            ]);
        }
    }

    /**
     * Work out what the customer paid per passenger, in the currency they paid in.
     * A stored passenger total_amount wins; otherwise the booking total is split evenly.
     *
     * @return array{0: ?string, 1: array<int, float>}
     */
    public function passengerAmounts(FlightBookings $booking, Collection $passengers): array
    {
        $snapshot = $booking->priceSnapshot;
        $bookingTotal = (float) ($snapshot?->selling_amount ?? $booking->selling_amount ?? $booking->amount ?? 0);
        $currency = $snapshot?->selling_currency ?? $booking->selling_currency;
        $evenShare = $passengers->count() > 0 ? round($bookingTotal / $passengers->count(), 2) : 0.0;

        $amounts = $passengers->mapWithKeys(function ($pax) use ($evenShare) {
            $stored = (float) ($pax->total_amount ?? 0);

            return [$pax->id => $stored > 0 ? round($stored, 2) : $evenShare];
        })->all();

        return [$currency ? strtoupper($currency) : null, $amounts];
    }

    private function notifyAdmin(BookingRequest $request): void
    {
        if (!config('app.booking_email_notifications', true)) {
            return;
        }

        $adminEmail = env('ADMIN_EMAIL') ?: User::where('role', 'admin')->value('email');

        try {
            if ($adminEmail) {
                Mail::to($adminEmail)->queue(new BookingRequestSubmittedMail($request));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to queue booking request submitted email: ' . $e->getMessage());
        }
    }

    private function notifyRequester(BookingRequest $request): void
    {
        if (!config('app.booking_email_notifications', true)) {
            return;
        }

        try {
            if ($request->requester?->email) {
                Mail::to($request->requester->email)->queue(new BookingRequestDecisionMail($request));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to queue booking request decision email: ' . $e->getMessage());
        }
    }
}
