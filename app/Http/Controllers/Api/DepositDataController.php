<?php

namespace App\Http\Controllers\Api;

use App\Mail\DepositRequestMail;
use App\Mail\DepositRequestReceivedMail;
use App\Mail\DepositApprovedMail;
use App\Mail\DepositStatusMail;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\DepositData;
use Log;
use Mail;
use Storage;
use Validator;

class DepositDataController extends Controller
{
    // Display a listing of the deposit data// Store a new deposit
    public function store(Request $request)
    {
        Log::info($request);

        $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'receipt_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validates uploaded image
            'payment_type' => 'required|string',
            'additional_details' => 'nullable|string',
            'agent_id' => 'required|integer|exists:users,id', // Assuming agents are in the users table
        ]);

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
            'agent_id' => $request->agent_id,
            'currency' => $request->currency,
        ]);

        $deposit = DepositData::with('agent.agentData')->find($deposit->id);
        $this->sendDepositRequestMail($deposit);
        $this->sendDepositRequestReceivedMail($deposit);

        return response()->json([
            'message' => 'Deposit created successfully',
            'deposit' => $deposit,
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

    public function getAgentDeposits(Request $request)
    {

        $user = auth()->user();

        if ($user->role === 'admin') {
            $deposits = DepositData::with('agent.agentData')
                ->orderBy('date', 'desc')
                ->get();

            return response()->json([
                'message' => 'All deposits retrieved successfully',
                'deposits' => $deposits,
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
                ->with('agent.agentData') // Include agent and agentData relationship
                ->orderBy('date', 'desc')
                ->get();

            $totalApprovedDeposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'approved') // Filter deposits by approved status
                ->sum('amount');
            $totalPendingDeposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'pending') // Filter deposits by approved status
                ->sum('amount');

            return response()->json([
                'message' => 'Agent deposits retrieved successfully',
                'deposits' => $deposits,
                'agent' => $agent,
                'totalApprovedDeposits' => $totalApprovedDeposits,
                'totalPendingDeposits' => $totalPendingDeposits, // Include the agent's details
            ]);
        }
    }

    public function getAllDepositsWithAgentData()
    {
        // Fetch all deposits, include agent and their agentData
        $deposits = DepositData::with(['agent.agentData'])
            ->orderBy('date', 'desc')
            ->get();

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

    public function getDepositDetails(Request $request)
    {
        //Log::info($request);
        // Fetch the deposit with agent and agent data
        $deposit = DepositData::with(['agent.agentData','agent.customer'])->find($request->DepositId);

        // Check if the deposit exists
        if (!$deposit) {
            return response()->json([
                'message' => 'Deposit not found',
            ], 404);
        }

        // Return the deposit details
        return response()->json([
            'message' => 'Deposit details retrieved successfully',
            'deposit' => $deposit,
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

    public function updateDepositStatus(Request $request)
    {
        // Validate the request
        $request->validate([
            'depositId' => 'required|exists:deposit_data,id',
            'status' => 'required|integer|in:0,1,2', // 0 = pending, 1 = approved, 2 = rejected
            'rejectionReason' => 'nullable|string|max:255',
        ]);

        // Find the deposit record
        $deposit = DepositData::find($request->depositId);

        // Set deposit_status based on the value of status
        if ($request->status == 0) {
            $deposit->deposit_status = 'pending'; // Set to 'pending' if status is 0
            $deposit->rejection_reason = null;   // Clear any rejection reason
        } elseif ($request->status == 1) {
            $deposit->deposit_status = 'approved'; // Set to 'approved' if status is 1
            $deposit->rejection_reason = null;    // Clear any rejection reason
        } elseif ($request->status == 2) {
            $deposit->deposit_status = 'rejected'; // Set to 'rejected' if status is 2
            $deposit->rejection_reason = $request->rejectionReason; // Set rejection reason if provided
        }

        // Save the updated deposit record
        $deposit->save();

        $deposit = DepositData::with('agent.agentData')->find($deposit->id);
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
            'deposit' => $deposit,
        ]);
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
                ->sum('amount'); // Calculate the total amount of approved deposits
                $totalDeposits = DepositData:: sum('amount'); // Calculate the total amount of approved deposits
                $totalRejectedDeposits = DepositData::where('deposit_status', 'rejected') // Filter deposits by approved status
                ->sum('amount');
            $totalPendingDeposits = DepositData::where('deposit_status', 'pending') // Filter deposits by approved status
                ->sum('amount');
        } else {
            // Fetch approved deposits for the agent and calculate the total
            $totalApprovedDeposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'approved') // Filter deposits by approved status
                ->sum('amount'); // Calculate the total amount of approved deposits

            $totalPendingDeposits = DepositData::where('agent_id', $agentId)
                ->where('deposit_status', 'pending') // Filter deposits by approved status
                ->sum('amount');
                $totalDeposits = 0;
                $totalRejectedDeposits = 0;
        }

        
        return response()->json([
            'message' => 'Total approved deposits calculated successfully',
            'totalApprovedDeposits' => $totalApprovedDeposits,
            'totalPendingDeposits' => $totalPendingDeposits,
            'totalDeposits' => $totalDeposits,
            'totalRejectedDeposits' => $totalRejectedDeposits,
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
