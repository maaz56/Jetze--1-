<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Log;

class CurrencyController extends Controller
{

    public function index(Request $request)
    {
         if ($request->searchQuery) {
            $searchQuery = $request->searchQuery;
            $currency = Currency::where('code', $searchQuery);
             $currency = $currency->where('code', 'LIKE', '%' . $searchQuery . '%');
            $currency = $currency->get();
            return response()->json(['data' => $currency], 200);

         }
        //Log::info('Fetching all currencies');
        $currencies = Currency::all();
        return response()->json(['data' => $currencies], 200);
    }       
    public function store(Request $request)
    {
        //Log::info('Storing new currency', $request->all());
        $validated = $request->validate([
            'code' => 'required|string|unique:currencies,code',
            'name' => 'nullable|string',
            'symbol' => 'nullable|string',
            'exchange_rate' => 'nullable|numeric',
        ]);

        $currency = Currency::create($validated);

        return response()->json(['message' => 'Currency created successfully', 'data' => $currency], 201);
    }

    public function update(Request $request)
    {

        $validated = $request->validate([
            'code' => 'required|string|exists:currencies,code',
            'name' => 'nullable|string',
            'symbol' => 'nullable|string',
            'exchange_rate' => 'nullable|numeric',
        ]);

        $currency = Currency::where('code', $validated['code'])->first();
        if (!$currency) {
            return response()->json(['message' => 'Currency not found'], 404);
        }

        $currency->update($validated);

        return response()->json(['message' => 'Currency updated successfully', 'data' => $currency], 200);
    }

    public function destroy(Request $request)
    {
        Log::info('Deleting currency', $request->all());
        $validated = $request->validate([
            'code' => 'required|string|exists:currencies,code',
        ]);

        $currency = Currency::where('code', $validated['code'])->first();
        if (!$currency) {
            return response()->json(['message' => 'Currency not found'], 404);
        }

        $currency->delete();

        return response()->json(['message' => 'Currency deleted successfully'], 200);
    }
}
