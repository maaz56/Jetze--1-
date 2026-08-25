<?php

namespace App\Http\Controllers\Api;

use App\Mail\DepositRequestMail;
use App\Mail\DepositRequestReceivedMail;
use App\Mail\DepositApprovedMail;
use App\Mail\DepositStatusMail;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\User;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\DepositData;
use Log;
use Mail;
use Storage;
use Validator;

class DepositDataController extends Controller
{
    /** Store a deposit with its source money and immutable AED accounting value. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'receipt_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validates uploaded image
            'payment_type' => 'required|string',
            'additional_details' => 'nullable|string',
            'agent_id' => 'nullable|integer|exists:users,id',
            'bank_id' => 'nullable|integer|exists:banks,id',
            'currency' => 'required|string|size:3',
        ]);

        $actor = $request->user();
        $agentId = $actor->role === 'admin'
            ? ($validated['agent_id'] ?? null)
            : $actor->id;

        if (!$agentId) {
            return response()->json(['message' => 'An agent is required for this deposit.'], 422);
        }

        $bank = empty($validated['bank_id'])
            ? null
            : Bank::query()
                ->whereKey($validated['bank_id'])
                ->where('is_active', true)
                ->firstOrFail();
        $currencyCode = strtoupper(trim($bank?->currency ?: $validated['currency']));
        $conversion = app(CurrencyConversionService::class);
        $baseMoney = $conversion->toBaseMoney($validated['amount'], $currencyCode);

        // Handle receipt image upload
        $receiptImagePath = null;
        if ($request->hasFile('receipt_image')) {
            $receiptImagePath = $request->file('receipt_image')->store('receipts', 'public'); // Save to public storage
        }

        $receiptUrl = $receiptImagePath ? Storage::url($receiptImagePath) : null;


        // Create the deposit record
        $deposit = DepositData::create([
            'date' => $request->date,
            'amount' => $request->amount,
            'receipt_image' => $receiptUrl,
            'payment_type' => $request->payment_type,
            'additional_details' => $request->additional_details,
            'agent_id' => $agentId,
            'bank_id' => $bank?->id,
            'currency' => $currencyCode,
            'rate_to_aed' => $conversion->rateToBase($currencyCode),
            'aed_amount' => $baseMoney['amount'],
        ]);

        $deposit = DepositData::with(['agent.agentData', 'bank'])->find($deposit->id);
        $this->sendDepositRequestMail($deposit);
        $this->sendDepositRequestReceivedMail($deposit);

        return response()->json([
            'message' => 'Deposit created successfully',
            'deposit' => $this->presentDeposit($deposit, $request->currency_code),
        ], 201);
    }

    // public function getAgentDeposits(Request $request)
    // {
    //     if ($request->userId === null) {
    //         Log::info("Null request");
    //         $deposits = DepositData::orderBy('date', 'desc') ->get();
    //         return response()->json([
    //             'message' => 'Agents deposits retrieved successfully',
    //             'deposits' => $deposits,
    //         ]);
    //     } else {
    //         $agentId = $request->userId;
    //         $agent = User::with('agentData')->find($agentId); // Assuming agents are stored in the users table
    //         if (!$agent) {
    //             return response()->json([
    //                 'message' => 'Agent not found',
    //             ], 404);
    //         }
    //         $deposits = DepositData::where('agent_id', $agentId)
    //             ->orderBy('date', 'desc') // Optional: Order by date descending
    //             ->get();
    //         return response()->json([
    //             'message' => 'Agent deposits retrieved successfully',
    //             'deposits' => $deposits,
    //         ]);
    //     }

    // }

    /** Return deposits and AED-based totals in the requested display currency. */
    public function getAgentDeposits(Request $request)
    {

        $user = auth()->user();

        if ($user->role === 'admin') {
            $depositQuery = DepositData::with(['agent.agentData', 'bank'])
                ->when($request->userId, fn ($query) => $query->where('agent_id', $request->userId));

            $totalApprovedDeposits = (clone $depositQuery)
                ->where('deposit_status', 'approved')
                ->sum('aed_amount');
            $totalPendingDeposits = (clone $depositQuery)
                ->where('deposit_status', 'pending')
                ->sum('aed_amount');

            $deposits = $depositQuery
                ->orderBy('date', 'desc')
                ->get()
                ->map(fn (DepositData $deposit) => $this->presentDeposit($deposit, $request->currency_code));

            return response()->json([
                'message' => 'All deposits retrieved successfully',
                'deposits' => $deposits,
                'totalApprovedDeposits' => $this->displayMoney($totalApprovedDeposits, $request->currency_code),
                'totalPendingDeposits' => $this->displayMoney($totalPendingDeposits, $request->currency_code),
                'legacy_unconverted_count' => (clone $depositQuery)
                    ->whereNull('aed_amount')
                    ->count(),
            ]);


        } else {
            $agentId = $user->id;
            $agent = User::with('agentData')->find($agentId);
            // $agentId = $request->userId;
           // $agent = User::with('agentData')->find($agentId); // Ensure the relationship is loaded
            if (!$agent) {
                return response()->json([
                    'message' => 'Agent not found',
                ], 404);
            }

            $deposits = DepositData::where('agent_id', $agentId)
                ->with(['agent.agentData', 'bank']) // Include agent and agentData relationship
                ->orderBy('date', 'desc')
                ->get();

            $totalApprovedDeposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'approved') // Filter deposits by approved status
                ->sum('aed_amount');
            $totalPendingDeposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'pending') // Filter deposits by approved status
                ->sum('aed_amount');

            return response()->json([
                'message' => 'Agent deposits retrieved successfully',
                'deposits' => $deposits->map(fn (DepositData $deposit) => $this->presentDeposit($deposit, $request->currency_code)),
                'agent' => $agent,
                'totalApprovedDeposits' => $this->displayMoney($totalApprovedDeposits, $request->currency_code),
                'totalPendingDeposits' => $this->displayMoney($totalPendingDeposits, $request->currency_code),
                'legacy_unconverted_count' => DepositData::where('agent_id', $agentId)
                    ->whereNull('aed_amount')
                    ->count(),
            ]);
        }
    }

    /** Return all deposits for admin review with their original and display money. */
    public function getAllDepositsWithAgentData(Request $request)
    {
        // Fetch all deposits, include agent and their agentData
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only an admin can view all deposits.');
        }

        $deposits = DepositData::with(['agent.agentData', 'bank'])
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn (DepositData $deposit) => $this->presentDeposit($deposit, 'AED'));

        // If no deposits found
        if ($deposits->isEmpty()) {
            return response()->json([
                'message' => 'No deposits found',
            ], 404);
        }

        // Return deposits with agent details
        return response()->json([
            'message' => 'All deposits with agent data retrieved successfully',
            'deposits' => $deposits,
        ]);
    }

    /** Return one deposit without converting its locked AED amount again. */
    public function getDepositDetails(Request $request)
    {
        //Log::info($request);
        // Fetch the deposit with agent and agent data
        $deposit = DepositData::with(['agent.agentData', 'agent.customer', 'bank', 'approver'])
            ->when($request->user()->role !== 'admin', fn ($query) => $query->where('agent_id', $request->user()->id))
            ->find($request->DepositId);

        // Check if the deposit exists
        if (!$deposit) {
            return response()->json([
                'message' => 'Deposit not found',
            ], 404);
        }

        // Return the deposit details
        return response()->json([
            'message' => 'Deposit details retrieved successfully',
            'deposit' => $this->presentDeposit($deposit, $request->currency_code),
        ]);
    }

    // public function updateDepositStatus(Request $request)
    // {


    //     // Find the deposit record
    //     $deposit = DepositData::find($request->depositId);

    //     if (!$deposit) {
    //         return response()->json([
    //             'message' => 'Deposit not found',
    //         ], 404);
    //     }

    //     // Set deposit_status based on the value of status
    //     if ($request->status == 0) {
    //         $deposit->deposit_status = 'pending'; // Set to 'pending' if status is 0
    //     } else {
    //         $deposit->deposit_status = 'approved'; // Set to 'approved' if status is 1
    //     }

    //     // Save the updated deposit record
    //     $deposit->save();

    //     // Log the updated deposit status
    //     Log::info('Updated Deposit Status:', ['deposit_id' => $deposit->id, 'new_status' => $deposit->deposit_status]);

    //     return response()->json([
    //         'message' => 'Deposit status updated successfully',
    //         'deposit' => $deposit,
    //     ]);
    // }

    /** Approve or reject a deposit without changing its locked conversion values. */
    public function updateDepositStatus(Request $request)
    {
        // Validate the request
        $request->validate([
            'depositId' => 'required|exists:deposit_data,id',
            'status' => 'required|integer|in:0,1,2', // 0 = pending, 1 = approved, 2 = rejected
            'rejectionReason' => 'nullable|string|max:255',
        ]);

        if ($request->user()->role !== 'admin') {
            abort(403, 'Only an admin can update deposit status.');
        }

        // Find the deposit record
        $deposit = DepositData::find($request->depositId);

        // Set deposit_status based on the value of status
        if ($request->status == 0) {
            $deposit->deposit_status = 'pending'; // Set to 'pending' if status is 0
            $deposit->rejection_reason = null;   // Clear any rejection reason
            $deposit->approved_by = null;
            $deposit->approved_at = null;
        } elseif ($request->status == 1) {
            $deposit->deposit_status = 'approved'; // Set to 'approved' if status is 1
            $deposit->rejection_reason = null;    // Clear any rejection reason
            $deposit->approved_by = $request->user()->id;
            $deposit->approved_at = Carbon::now();
        } elseif ($request->status == 2) {
            $deposit->deposit_status = 'rejected'; // Set to 'rejected' if status is 2
            $deposit->rejection_reason = $request->rejectionReason; // Set rejection reason if provided
            $deposit->approved_by = null;
            $deposit->approved_at = null;
        }

        // Save the updated deposit record
        $deposit->save();

        $deposit = DepositData::with(['agent.agentData', 'bank', 'approver'])->find($deposit->id);
        if ($deposit->deposit_status === 'approved') {
            $this->sendDepositApprovedMail($deposit);
        } elseif ($deposit->deposit_status === 'rejected') {
            $this->sendDepositStatusMail($deposit);
        }

        // Log the updated deposit status
        // Log::info('Updated Deposit Status:', [
        //     'deposit_id' => $deposit->id,
        //     'new_status' => $deposit->deposit_status,
        //     'rejection_reason' => $deposit->rejection_reason,
        // ]);

        return response()->json([
            'message' => 'Deposit status updated successfully',
            'deposit' => $this->presentDeposit($deposit, 'AED'),
        ]);
    }

    /** Attach original, locked AED, and selected-currency money to a deposit response. */
    private function presentDeposit(DepositData $deposit, ?string $displayCurrency): DepositData
    {
        $deposit->setAttribute('source_money', app(CurrencyConversionService::class)->makeMoney(
            $deposit->amount,
            $deposit->currency ?: 'AED',
        ));
        $deposit->setAttribute('base_money', $deposit->aed_amount === null
            ? null
            : app(CurrencyConversionService::class)->makeMoney($deposit->aed_amount, 'AED'));
        $deposit->setAttribute('display_money', $deposit->aed_amount === null
            ? null
            : $this->displayMoney($deposit->aed_amount, $displayCurrency));
        $deposit->setAttribute('legacy_unconverted', $deposit->aed_amount === null);

        return $deposit;
    }

    /** Convert a trusted AED amount only for display. */
    private function displayMoney(mixed $aedAmount, ?string $displayCurrency): array
    {
        return app(CurrencyConversionService::class)->convertMoney(
            $aedAmount ?? 0,
            'AED',
            $displayCurrency ?: 'AED',
        );
    }

    private function sendDepositRequestMail($deposit): void
    {
        if (!$deposit) {
            return;
        }

        $admin = User::where('role', 'admin')->first();
        $adminEmail = $admin->email ?? null;
        if (empty($adminEmail)) {
            return;
        }

        Mail::to($adminEmail)->queue(
            (new DepositRequestMail($adminEmail, $deposit))->afterCommit()
        );
    }

    private function sendDepositRequestReceivedMail($deposit): void
    {
        if (!$deposit) {
            return;
        }

        $customerEmail = $deposit->agent->email ?? null;
        if (empty($customerEmail)) {
            return;
        }

        Mail::to($customerEmail)->queue(
            (new DepositRequestReceivedMail($customerEmail, $deposit))->afterCommit()
        );
    }

    private function sendDepositStatusMail($deposit): void
    {
        if (!$deposit) {
            return;
        }

        $customerEmail = $deposit->agent->email ?? null;
        if (empty($customerEmail)) {
            return;
        }

        Mail::to($customerEmail)->queue(
            (new DepositStatusMail($customerEmail, $deposit))->afterCommit()
        );
    }

    private function sendDepositApprovedMail($deposit): void
    {
        if (!$deposit) {
            return;
        }

        $customerEmail = $deposit->agent->email ?? null;
        if (empty($customerEmail)) {
            return;
        }

        Mail::to($customerEmail)->queue(
            (new DepositApprovedMail($customerEmail, $deposit))->afterCommit()
        );
    }


    public function getApprovedDepositsTotal(Request $request)
    {
      
        
        $agentId = $request->userId;

        if (!$agentId) {
            $totalApprovedDeposits = DepositData::where('deposit_status', 'approved') // Filter deposits by approved status
                ->sum('aed_amount'); // Calculate AED value of approved deposits
                $totalDeposits = DepositData::sum('aed_amount'); // Calculate AED value of all deposits
                $totalRejectedDeposits = DepositData::where('deposit_status', 'rejected') // Filter deposits by approved status
                ->sum('aed_amount');
            $totalPendingDeposits = DepositData::where('deposit_status', 'pending') // Filter deposits by approved status
                ->sum('aed_amount');
        } else {
            // Fetch approved deposits for the agent and calculate the total
            $totalApprovedDeposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'approved') // Filter deposits by approved status
                ->sum('aed_amount'); // Calculate AED value of approved deposits

            $totalPendingDeposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'pending') // Filter deposits by approved status
                ->sum('aed_amount');

            $totalRejectedDeposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'rejected')
                ->sum('aed_amount');

            $totalDeposits = DepositData::where('agent_id', $agentId)
                ->sum('aed_amount');
        }

        
        $conversion = app(CurrencyConversionService::class);

        return response()->json([
            'message' => 'Total approved deposits calculated successfully',
            'totalApprovedDeposits' => $conversion->makeMoney($totalApprovedDeposits ?? 0, 'AED'),
            'totalPendingDeposits' => $conversion->makeMoney($totalPendingDeposits ?? 0, 'AED'),
            'totalDeposits' => $conversion->makeMoney($totalDeposits ?? 0, 'AED'),
            'totalRejectedDeposits' => $conversion->makeMoney($totalRejectedDeposits ?? 0, 'AED'),
            'totalApprovedDepositsAmount' => (float) ($totalApprovedDeposits ?? 0),
            'totalPendingDepositsAmount' => (float) ($totalPendingDeposits ?? 0),
            'totalDepositsAmount' => (float) ($totalDeposits ?? 0),
            'totalRejectedDepositsAmount' => (float) ($totalRejectedDeposits ?? 0),
            'currency' => 'AED',
        ]);
    }


    public function destroy(Request $request)
    {

        Log::info($request);

        // Find the deposit by ID
        $deposit = DepositData::find($request->id);

        if (!$deposit) {
            return response()->json([
                'message' => 'Deposit not found',
            ], 404);
        }
        // Delete receipt image from storage if it exists
        if ($deposit->receipt_image) {
            $receiptPath = str_replace('/storage/', 'public/', $deposit->receipt_image);
            if (Storage::exists($receiptPath)) {
                Storage::delete($receiptPath);
            }
        }

        // Delete the deposit record
        $deposit->delete();

        return response()->json([
            'message' => 'Deposit deleted successfully',
        ], 200);
    }
}
