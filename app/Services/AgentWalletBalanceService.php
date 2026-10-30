<?php

namespace App\Services;

use App\Models\AdminBooking;
use App\Models\AgentCharge;
use App\Models\AgentLedgerEntry;
use App\Models\DepositData;
use App\Models\FlightBookings;
use App\Models\HotelBooking;
use App\Models\OfflineBooking;

class AgentWalletBalanceService
{
    /** Calculate an agent's available wallet balance in AED accounting values. */
    public function balanceInAed(int $agentId): string
    {
        $credits = [
            DepositData::where('agent_id', $agentId)->where('deposit_status', 'approved')->sum('aed_amount'),
            AgentCharge::where('user_id', $agentId)->where('is_approved', 1)
                ->whereIn('payment_type', ['refund', 'void'])->sum('amount'),
            AgentLedgerEntry::where('agent_id', $agentId)->where('direction', 'credit')->sum('aed_amount'),
        ];

        $debits = [
            FlightBookings::where('agent_id', $agentId)
                ->chargedToWallet()
                ->selectRaw('COALESCE(SUM(COALESCE(aed_amount, amount)), 0) as total')->value('total'),
            HotelBooking::query()
                ->join('hotel_booking_price_snapshots', 'hotel_booking_price_snapshots.hotel_booking_id', '=', 'hotel_bookings.id')
                ->where('hotel_bookings.user_id', $agentId)
                ->where('hotel_bookings.status', 'confirmed')
                ->where('hotel_bookings.payment_method', 'wallet')
                ->sum('hotel_booking_price_snapshots.aed_amount'),
            OfflineBooking::where('agent_id', $agentId)->sum('amount'),
            AdminBooking::where('agent_id', $agentId)->selectRaw('COALESCE(SUM(total_amount + margin), 0) as total')->value('total'),
            AgentCharge::where('user_id', $agentId)->where('is_approved', 1)
                ->whereIn('payment_type', ['charge', 'ok_to_board', 're_issue', 'umrah', 'visa'])->sum('amount'),
            AgentLedgerEntry::where('agent_id', $agentId)->where('direction', 'debit')->sum('aed_amount'),
        ];

        $balance = '0.00000000';

        foreach ($credits as $credit) {
            $balance = bcadd($balance, (string) ($credit ?? 0), 8);
        }

        foreach ($debits as $debit) {
            $balance = bcsub($balance, (string) ($debit ?? 0), 8);
        }

        return $balance;
    }

    /** Confirm that an agent can cover a booking's locked AED amount. */
    public function canPayForBooking(FlightBookings $booking): bool
    {
        $booking->loadMissing('priceSnapshot');
        $requiredAmount = $booking->priceSnapshot?->aed_amount ?? $booking->aed_amount;

        if ($requiredAmount === null || $booking->agent_id === null) {
            return false;
        }

        return bccomp($this->balanceInAed((int) $booking->agent_id), (string) $requiredAmount, 8) >= 0;
    }

    /** Confirm that a user can cover a hotel's immutable AED selling amount. */
    public function canPayForHotelBooking(HotelBooking $booking): bool
    {
        $booking->loadMissing('priceSnapshot');
        $requiredAmount = $booking->priceSnapshot?->aed_amount;

        if ($requiredAmount === null || $booking->user_id === null || bccomp((string) $requiredAmount, '0', 8) <= 0) {
            return false;
        }

        return bccomp($this->balanceInAed((int) $booking->user_id), (string) $requiredAmount, 8) >= 0;
    }
}
