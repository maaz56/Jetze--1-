<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminBooking;
use App\Models\AgentCharge;
use App\Models\DepositData;
use App\Models\FlightBookings;
use App\Models\OfflineBooking;
use DB;
use Illuminate\Http\Request;
use Log;

class AgentLedgerController extends Controller
{

    public function index(Request $request)
    {

       // Log::info($request->all());

        $userRole = $request->userRole;
        $agentId = $request->userId;
        $startDate = $request->startDate;
        $endDate = $request->endDate;

        // Fetch deposits (credits)
        if ($userRole === 'admin') {
            // Fetch all deposits with user data
            $deposits = DepositData::with('agent.agentData')
                ->where('deposit_status', 'approved')
                ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
                ->select([
                    'date',
                    DB::raw('NULL as debit'),
                    'amount as credit',
                    DB::raw('"deposit" as transaction_type'),
                    'receipt_reference as reference_id',
                    'additional_details as details',
                    DB::raw('id as record_id'),
                    DB::raw('NULL as booking_source'),
                    DB::raw('NULL as flight_provider'),
                    DB::raw('NULL as pnr_ref'),
                    'agent_id',
                ]);

            // Fetch all flight bookings with user data
            $flightBookings = FlightBookings::with('agent.agentData')
                ->whereIn('status', ['ticketed', 'issued','voided'])
                // Card/One-bill approved transactions are paid externally,
                // so they should not reduce agent wallet ledger balance.
                ->where(function ($q) {
                    $q->whereNull('t_status')
                        ->orWhere('t_status', '!=', 'approved');
                })
                // If booking is issued and has external transaction id,
                // treat it as externally paid (card/bank) and skip wallet debit.
                ->where(function ($q) {
                    $q->whereNull('tid')
                        ->orWhere('status', '!=', 'issued');
                })
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                ->select([
                    'created_at as date',
                    DB::raw('CASE WHEN is_manually_issued = 1 THEN 0 ELSE amount END as debit'),
                    DB::raw('NULL as credit'),
                    DB::raw('CASE WHEN is_manually_issued = 1 THEN "manually_issued" ELSE "booking" END as transaction_type'),
                    'id as reference_id',
                    DB::raw('CASE WHEN is_manually_issued = 1 THEN "manually issued" ELSE flight_id END as details'),
                    DB::raw('id as record_id'),
                    'booking_source',
                    'flight_provider',
                    'itinerary_ref as pnr_ref',
                    'agent_id',
                ]);
            $offlineBookings = OfflineBooking::where('agent_id', $agentId)
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                ->select([
                    'created_at as date',
                    'amount as debit',
                    DB::raw('NULL as credit'),
                    DB::raw('"offline_booking" as transaction_type'),
                    'id as reference_id',
                    // FIX: offline bookings don’t have flight_id
                    DB::raw('route as details'), // 👈 use route instead
                    DB::raw('id as record_id'),
                    DB::raw('NULL as booking_source'),
                    DB::raw('NULL as flight_provider'),
                    'booking_pnr as pnr_ref',
                    'agent_id',
                ]);
            // Fetch all admin bookings with user data
            $adminBookings = AdminBooking::with('agent.agentData')
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                ->select([
                    'created_at as date',
                    DB::raw('(total_amount + margin) as debit'),
                    DB::raw('NULL as credit'),
                    DB::raw('"direct_booking" as transaction_type'),
                    'id as reference_id',
                    'pnr as details',
                    DB::raw('id as record_id'),
                    DB::raw('NULL as booking_source'),
                    DB::raw('NULL as flight_provider'),
                    'pnr as pnr_ref',
                    'agent_id',
                ]);

            // Fetch all agent charges with user data
            $agentCharges = AgentCharge::with('agent.agentData')
                ->where('is_approved', 1)
                ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
                ->select([
                    'date',
                    DB::raw('CASE WHEN payment_type IN ("charge", "ok_to_board", "re_issue", "umrah", "visa") THEN amount ELSE NULL END as debit'),
                    DB::raw('CASE WHEN payment_type IN ("refund", "void") THEN amount ELSE NULL END as credit'),
                    DB::raw('payment_type as transaction_type'),
                    'id as reference_id',
                    'additional_details as details',
                    DB::raw('id as record_id'),
                    DB::raw('NULL as booking_source'),
                    DB::raw('NULL as flight_provider'),
                    DB::raw('NULL as pnr_ref'),
                    'user_id as agent_id',
                ]);

        } else {
            // Fetch deposits (credits)
            $deposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'approved')
                ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
                ->select([
                    'date',
                    DB::raw('NULL as debit'),
                    'amount as credit',
                    DB::raw('"deposit" as transaction_type'),
                    'receipt_reference as reference_id',
                    'additional_details as details',
                    DB::raw('id as record_id'),
                    DB::raw('NULL as booking_source'),
                    DB::raw('NULL as flight_provider'),
                    DB::raw('NULL as pnr_ref'),
                ]);

            // Fetch bookings (debits)
            $flightBookings = FlightBookings::where('agent_id', $agentId)
                ->whereIn('status', ['ticketed', 'issued','voided'])
                // Card/One-bill approved transactions are paid externally,
                // so they should not reduce agent wallet ledger balance.
                ->where(function ($q) {
                    $q->whereNull('t_status')
                        ->orWhere('t_status', '!=', 'approved');
                })
                // If booking is issued and has external transaction id,
                // treat it as externally paid (card/bank) and skip wallet debit.
                ->where(function ($q) {
                    $q->whereNull('tid')
                        ->orWhere('status', '!=', 'issued');
                })
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                ->select([
                    'created_at as date',
                    DB::raw('CASE WHEN is_manually_issued = 1 THEN 0 ELSE amount END as debit'),
                    DB::raw('NULL as credit'),
                    DB::raw('CASE WHEN is_manually_issued = 1 THEN "manually_issued" ELSE "booking" END as transaction_type'),
                    'id as reference_id',
                    DB::raw('CASE WHEN is_manually_issued = 1 THEN "manually issued" ELSE flight_id END as details'),
                    DB::raw('id as record_id'),
                    'booking_source',
                    'flight_provider',
                    'itinerary_ref as pnr_ref',
                ]);
            $offlineBookings = OfflineBooking::where('agent_id', $agentId)
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                ->select([
                    'created_at as date',
                    'amount as debit',
                    DB::raw('NULL as credit'),
                    DB::raw('"offline_booking" as transaction_type'),
                    'id as reference_id',
                    // FIX: offline bookings don’t have flight_id
                    DB::raw('"offline_booking" as details'), // 👈 use route instead
                    DB::raw('id as record_id'),
                    DB::raw('NULL as booking_source'),
                    DB::raw('NULL as flight_provider'),
                    'booking_pnr as pnr_ref',
                ]);

            // Fetch admin bookings (debits)
            $adminBookings = AdminBooking::where('agent_id', $agentId)
                ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                ->select([
                    'created_at as date',
                    DB::raw('(total_amount + margin) as debit'),
                    DB::raw('NULL as credit'),
                    DB::raw('"direct_booking" as transaction_type'),
                    'id as reference_id',
                    'pnr as details',
                    DB::raw('id as record_id'),
                    DB::raw('NULL as booking_source'),
                    DB::raw('NULL as flight_provider'),
                    'pnr as pnr_ref',
                ]);

            // Fetch agent charges (debits and credits)
            $agentCharges = AgentCharge::where('user_id', $agentId)
                ->where('is_approved', 1)
                ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
                ->select([
                    'date',
                    DB::raw('CASE WHEN payment_type IN ("charge", "ok_to_board", "re_issue", "umrah", "visa") THEN amount ELSE NULL END as debit'),
                    DB::raw('CASE WHEN payment_type IN ("refund", "void") THEN amount ELSE NULL END as credit'),
                    DB::raw('payment_type as transaction_type'),
                    'id as reference_id',
                    'additional_details as details',
                    DB::raw('id as record_id'),
                    DB::raw('NULL as booking_source'),
                    DB::raw('NULL as flight_provider'),
                    DB::raw('NULL as pnr_ref'),
                ]);
        }

        // Combine all transactions
        $transactions = $deposits
            ->unionAll($flightBookings)
            ->unionAll($adminBookings)
            ->unionAll($agentCharges)
            ->unionAll($offlineBookings)
            ->orderBy('date', 'asc')
            ->get();
        $balance = 0;

        if ($request->userRole === 'admin') {
            $ledger = $transactions->map(function ($transaction) use (&$balance) {
                $transaction->debit = $transaction->debit ?? 0;
                $transaction->credit = $transaction->credit ?? 0;
                $balance += $transaction->debit - $transaction->credit;
                $transaction->balance = $balance;
                return $transaction;
            });
        } else {
            $ledger = $transactions->map(function ($transaction) use (&$balance) {
                $transaction->debit = $transaction->debit ?? 0;
                $transaction->credit = $transaction->credit ?? 0;
                $balance += $transaction->credit - $transaction->debit;
                $transaction->balance = $balance;
                return $transaction;
            });
        }



        return response()->json([
            'status' => 'success',
            'ledger' => $ledger,
            'balance' => $balance,
        ]);
    }

    public function profitLossReport(Request $request)
    {
        $query = FlightBookings::with(['user.agentData'])->whereIn('status', ['ticketed', 'issued']);
        // ✅ Filter by agent_id
        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        // ✅ Filter by date range
        if ($request->filled('startDate') && $request->filled('endDate')) {
            $query->whereBetween('created_at', [$request->startDate, $request->endDate]);
        }

        // ✅ Fetch bookings
        $bookings = $query->get();

        $report = $bookings->groupBy('agent_id')->map(function ($agentBookings, $agentId) {
            $totalRevenue = 0;
            $totalProfit = 0;
            $totalAgentMarkup = 0;
            $totalAgentMargin = 0;
            $totalAirlineMargin = 0;

            foreach ($agentBookings as $booking) {
                $totalRevenue += $booking->amount;
                $totalAgentMarkup += $booking->agent_markup;
                $totalAgentMargin += $booking->agent_margin;

                // ✅ Decode flight_data JSON
                if (!empty($booking->flight_data)) {
                    $flightData = json_decode($booking->flight_data, true);

                    if (isset($flightData['leg']['flights'])) {
                        foreach ($flightData['leg']['flights'] as $flight) {
                            if (isset($flight['fares']) && is_array($flight['fares'])) {
                                foreach ($flight['fares'] as $fare) {
                                    $basePrice = $fare['base_price'] ?? 0;
                                    $marginAmount = (float) ($fare['margin_amount'] ?? 0);
                                    $amountType = $fare['amount_type'] ?? 'amount';
                                    $marginType = $fare['margin_type'] ?? 'markup';

                                    $margin = 0;

                                    if ($amountType === 'percent') {
                                        $margin = ($marginAmount / 100) * $basePrice;
                                    } else {
                                        $margin = $marginAmount;
                                    }

                                    // ✅ Adjust for markup vs discount
                                    if ($marginType === 'discount') {
                                        $margin = -$margin;
                                    }

                                    $totalAirlineMargin += $margin;
                                }
                            }
                        }
                    }
                }
            }
            $totalProfit = $totalAgentMarkup;
            $totalAgentProfit = $totalAgentMargin;

            return [
                'agent_id' => $agentId,
                'user' => $agentBookings->first()->user,
                'agentData' => $agentBookings->first()->user?->agentData, // ✅ include user
                'total_bookings' => $agentBookings->count(),
                'total_revenue' => $totalRevenue,
                'total_profit' => $totalProfit,
                'total_agent_margin' => $totalAgentMargin,
                'total_cost' => $totalRevenue - $totalProfit - $totalAirlineMargin,
                'total_airline_margin' => $totalAirlineMargin,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }





}
