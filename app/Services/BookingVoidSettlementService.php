<?php

namespace App\Services;

use App\Models\AgentLedgerEntry;
use App\Models\BookingVoidSnapshot;
use App\Models\FlightBookings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingVoidSettlementService
{
    /**
     * Lock an AT wallet void in AED and create its refund and charge ledger entries.
     */
    public function settleAtWalletVoid(
        FlightBookings $booking,
        string $chargeAmount,
        string $effectiveDate,
        string $description,
        ?int $voidedBy,
    ): BookingVoidSnapshot {
        return DB::transaction(function () use ($booking, $chargeAmount, $effectiveDate, $description, $voidedBy) {
            $lockedBooking = FlightBookings::query()
                ->with('priceSnapshot')
                ->lockForUpdate()
                ->findOrFail($booking->id);

            $existing = BookingVoidSnapshot::query()
                ->where('booking_id', $lockedBooking->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === 'settled') {
                return $existing;
            }

            if (!in_array(strtolower((string) $lockedBooking->status), ['ticketed', 'issued'], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only ticketed or issued bookings can be voided.',
                ]);
            }

            $priceSnapshot = $lockedBooking->priceSnapshot;
            if (!$priceSnapshot) {
                throw ValidationException::withMessages([
                    'booking' => 'A locked booking price snapshot is required before voiding.',
                ]);
            }

            $total = (string) $priceSnapshot->aed_amount;
            $charge = bcadd($chargeAmount, '0', 8);

            if (bccomp($charge, '0', 8) < 0 || bccomp($charge, $total, 8) === 1) {
                throw ValidationException::withMessages([
                    'void_charge_aed' => 'Void charge must be between AED 0 and the locked booking total.',
                ]);
            }

            $refund = bcsub($total, $charge, 8);
            $idempotencyKey = 'at-void-settlement:' . $lockedBooking->id;

            $voidSnapshot = BookingVoidSnapshot::create([
                'booking_id' => $lockedBooking->id,
                'original_price_snapshot_id' => $priceSnapshot->id,
                'voided_by' => $voidedBy,
                'provider' => 'AT',
                'original_aed_amount' => $total,
                'void_charge_aed' => $charge,
                'refund_aed' => $refund,
                'currency' => 'AED',
                'effective_date' => $effectiveDate,
                'description' => $description,
                'status' => 'settled',
                'idempotency_key' => $idempotencyKey,
                'voided_at' => now(),
            ]);

            $this->createLedgerEntry(
                $lockedBooking,
                $voidSnapshot,
                'booking_refund',
                'credit',
                $total,
                $effectiveDate,
                $description,
            );

            $this->createLedgerEntry(
                $lockedBooking,
                $voidSnapshot,
                'void_charge',
                'debit',
                $charge,
                $effectiveDate,
                $description,
            );

            $lockedBooking->update(['status' => 'voided']);

            return $voidSnapshot;
        });
    }

    /** Create one immutable AED wallet entry for a settled booking void. */
    private function createLedgerEntry(
        FlightBookings $booking,
        BookingVoidSnapshot $voidSnapshot,
        string $entryType,
        string $direction,
        string $amount,
        string $effectiveDate,
        string $description,
    ): void {
        AgentLedgerEntry::create([
            'agent_id' => $booking->agent_id,
            'booking_id' => $booking->id,
            'booking_void_snapshot_id' => $voidSnapshot->id,
            'entry_type' => $entryType,
            'direction' => $direction,
            'aed_amount' => $amount,
            'currency' => 'AED',
            'description' => $description,
            'effective_date' => $effectiveDate,
            'idempotency_key' => $voidSnapshot->idempotency_key . ':' . $entryType,
        ]);
    }
}
