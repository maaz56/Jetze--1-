<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->user_id) {
            $transactions = Transaction::where('user_id', $request->user_id)->with('user')->orderBy('created_at')->get();
            return $transactions;
        }

        $transactions = Transaction::with('user')->orderBy('created_at')->get();
        return $transactions;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image',
            'receipt_no' => 'required',
            'bank' => 'required',
            'amount' => 'required|numeric',
            'payment_type' => 'required',
        ]);

        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->getClientOriginalExtension();
            $request->image->move(public_path('uploads'), $imageName);

            Transaction::create([
                'user_id' => $request->user_id,
                'image_name' => $imageName,
                'image_path' => env('APP_URL') .  '/uploads/' . $imageName,
                'receipt_no' => $request->receipt_no,
                'bank' => $request->bank,
                'amount' => $request->amount,
                'payment_type' => $request->payment_type,
                'details' => $request->details,
            ]);

            return response()->json([
                'message' => 'Transaction request has been created successfully, please wait for approval.',
            ], 201);
        } else {
            return response()->json([
                'message' => 'Receipt image is required',
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $transaction = Transaction::where('id', $request->transaction_id)->first();

        $transaction->update([
            'is_approved' => true
        ]);

        $user = User::where('id', $transaction->user->id)->first();
        $user->balance += $transaction->amount;
        $user->save();

        return [
            'message' => 'Transaction status updated successfully.',
        ];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
