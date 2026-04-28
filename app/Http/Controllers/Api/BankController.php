<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use Illuminate\Http\Request;
use Log;
use Storage;

class BankController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $banksQuery = Bank::query()->orderByDesc('updated_at');

        if (!$user || $user->role !== 'admin') {
            $banksQuery->where('is_active', true);
        }

        $banks = $banksQuery->get();

        return $banks;
    }

    public function store(Request $request)
    {
        Log::info($request);
        $validated = $request->validate([
            'bankName' => 'required|string',
            'accountTitle' => 'required|string',
            'accountNumber' => 'required|string',
            'currency' => 'required|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'is_active' => 'nullable|boolean',
        ]);


        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('public');
        }
        $logoUrl = $logoPath ? Storage::url($logoPath) : null;

        $bankAccount = Bank::create([
            'bank_name' => $validated['bankName'],
            'account_title' => $validated['accountTitle'],
            'account_number' => $validated['accountNumber'],
            'currency' => $validated['currency'],
            'logo_path' => $logoUrl,
            'iban' => $request->input('iban'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['message' => 'Bank account saved successfully', 'data' => $bankAccount]);
    
    }
    
    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:banks,id',
            'bankName' => 'sometimes|required|string',
            'accountTitle' => 'sometimes|required|string',
            'accountNumber' => 'sometimes|required|string',
            'currency' => 'sometimes|required|string',
            'iban' => 'nullable|string|max:34',
            'is_active' => 'sometimes|boolean',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $bank = Bank::findOrFail($validated['id']);

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('public');
            $bank->logo_path = Storage::url($logoPath);
        }

        if (array_key_exists('bankName', $validated)) {
            $bank->bank_name = $validated['bankName'];
        }
        if (array_key_exists('accountTitle', $validated)) {
            $bank->account_title = $validated['accountTitle'];
        }
        if (array_key_exists('accountNumber', $validated)) {
            $bank->account_number = $validated['accountNumber'];
        }
        if (array_key_exists('currency', $validated)) {
            $bank->currency = $validated['currency'];
        }
        if (array_key_exists('iban', $validated)) {
            $bank->iban = $validated['iban'];
        }
        if (array_key_exists('is_active', $validated)) {
            $bank->is_active = (bool) $validated['is_active'];
        }

        $bank->save();

        return response()->json(['message' => 'Bank account updated successfully', 'data' => $bank]);
    }

    public function destroy(Request $request,)
    {
        $bank = Bank::findOrFail($request->id);
        $bank->delete();
        return response()->json(['message' => 'Bank account delelted successfully', 'data' => $bank]);
    }
}
