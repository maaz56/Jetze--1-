<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use Illuminate\Http\Request;
use Log;

class AirlineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        Log::info($request);

        $searchQuery = $request->search;

        $airlines = Airline::where(
            'iata_code',
            'like',
            '%' . $searchQuery . '%'
        )->orWhere(
            'icao_code',
            'like',
            '%' . $searchQuery . '%'
        )->orWhere(
            'name',
            'like',
            '%' . $searchQuery . '%'
        )->orWhere(
            'logo_url',
            'like',
            '%' . $searchQuery . '%'
        )->orWhere(
            'carrier_condition_url',
            'like',
            '%' . $searchQuery . '%'
        )
        ->orderBy('updated_at', 'desc')
       
        ->get();

        return $airlines;
    }

    public function updateAirline(Request $request){

        Log::info($request);
        $request->validate([
            'type' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'amount_type'=>'nullable|string',
        ]);
    
        $airline = Airline::findOrFail($request->id);
    
        $airline->update([
            'margin_type' => $request->type,
            'margin_amount' => $request->amount,
            'amount_type'=> $request->amount_type
        ]);
    
        return response()->json([
            'message' => 'Airline updated successfully!',
            'airline' => $airline
        ], 200);
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
       // Log::info($request);
        $request->validate([
            'iata_code' => 'required|unique:airlines',
            'name' => 'required',
        ]);

    

        $airline = new Airline();
        $airline->iata_code = $request->iata_code;
        $airline->icao_code = $request->icaoCode;
        $airline->name = $request->name;
        $airline->logo_url = $request->logoUrl;
        $airline->carrier_condition_url = $request->carrierConditionUrl;
        $airline->save();
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
    // public function update(Request $request)
    // {
        
    //     $airline = Airline::findOrFail($request->id);

    //     $request->validate([
    //         'iata_code' => 'required|unique:airlines',
    //         'name' => 'required',
    //     ]);

    //     $airline->iata_code = $request->iata_code;
    //     $airline->icao_code = $request->icaoCode;
    //     $airline->name = $request->name;
    //     $airline->logo_url = $request->logoUrl;
    //     $airline->carrier_condition_url = $request->carrierConditionUrl;
    //     $airline->save();
    // }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        Log::info("Deleting airline with ID: " .  $request->id);
        $airline = Airline::findOrFail( $request->id);
        $airline->delete();
    }
}
