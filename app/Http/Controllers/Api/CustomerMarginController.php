<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerMargin;
use Illuminate\Http\Request;
use Log;

class CustomerMarginController extends Controller
{
    public function index()
    {
        return CustomerMargin::latest()->first();
    }

    public function update(Request $request)
    {
        Log::info('Update B2C request:', $request->all());
    
        $b2cValue = CustomerMargin::findOrFail($request->id);
    
        $b2cValue->discount = $request->discount;
        $b2cValue->other_charges = $request->otherCharges;  // note the camelCase request to snake_case DB field
        $b2cValue->margin_amount = $request->amount;
    
        $b2cValue->save();  // important: save the model after updating attributes
    
        return response()->json([
            'message' => 'Customer Margin Set successfully',
            'data' => $b2cValue,
        ]);
    }
}
