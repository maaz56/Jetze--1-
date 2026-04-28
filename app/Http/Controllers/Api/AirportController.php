<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Models\AirportMargin;
use Illuminate\Http\Request;
use Log;

class AirportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $airports = Airport::where(
        //     'city_name',
        //     'like',
        //     '%' . $searchQuery . '%'
        // )->orWhere(
        //     'name',
        //     'like',
        //     '%' . $searchQuery . '%'
        // )->orWhere(
        //     'iata_code',
        //     'like',
        //     '%' . $searchQuery . '%'
        // )->orWhere(
        //     'iata_city_code',
        //     'like',
        //     '%' . $searchQuery . '%'
        // )->orWhere(
        //     'iata_country_code',
        //     'like',
        //     '%' . $searchQuery . '%'
        // )->limit(Constants::$PAGE_LIMIT)->get();
        $airports = Airport::get();
        return $airports;

        return $airports->toArray();
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
            'iata_code' => 'unique:airports|iata_code',
            'name' => 'required',
        ]);

        $airport = new Airport();
        $airport->iata_city_code = $request->iata_city_code;
        $airport->iata_country_code = $request->iata_country_code;
        $airport->iata_code = $request->iata_code;
        $airport->city_name = $request->city_name;
        $airport->latitude = $request->latitude;
        $airport->longitude = $request->longitude;
        $airport->time_zone = $request->time_zonHomee;
        $airport->name = $request->name;
        $airport->save();
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
    public function update(Request $request, string $id)
    {
        $airport = Airport::findOrFail($request->id);

        $request->validate([
            'iata_code' => 'required|unique:iata_code',
            'name' => 'required',
        ]);

        $airport->iata_city_code = $request->iata_city_code;
        $airport->iata_country_code = $request->iata_country_code;
        $airport->iata_code = $request->iata_code;
        $airport->city_name = $request->city_name;
        $airport->latitude = $request->latitude;
        $airport->longitude = $request->longitude;
        $airport->time_zone = $request->time_zone;
        $airport->name = $request->name;
        $airport->save();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Airport::findOrFail($id)->delete();
    }


    public function storeMargins(Request $request)
    {
        Log::info('Storing airport margins', $request->all());

        // Validate incoming data
        $request->validate([
            'domestic' => 'required',
            'international' => 'required',
        ]);

        // Fetch existing record (should be only one)
        $airport = AirportMargin::first();

        if ($airport) {
            // Update existing record
            $airport->update([
                'domestic' => $request->domestic,
                'international' => $request->international,
            ]);
        } else {
            // Create a new record
            AirportMargin::create([
                'domestic' => $request->domestic,
                'international' => $request->international,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Margins saved successfully'
        ]);
    }

    public function getMargins()
    {
        $margins = AirportMargin::first();
        // Log::info($margins);
        return response()->json($margins);
    }


}
