<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use Illuminate\Http\Request;

class AircraftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->search_query;

        $aircraft = Aircraft::where(
            'iata_code',
            'like',
            '%' . $searchQuery . '%'
        )->orWhere(
            'name',
            'like',
            '%' . $searchQuery . '%'
        )->limit(Constants::$PAGE_LIMIT)->get();

        return $aircraft;
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
            'iata_code' => 'unique:aircrafts|iata_code',
            'name' => 'required',
        ]);

        $aircraft = new Aircraft();
        $aircraft->iata_code = $request->iata_code;
        $aircraft->name = $request->name;
        $aircraft->save();
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
        $aircraft = Aircraft::findOrFail($request->id);
    
        $request->validate([
            'iata_code' => 'required|unique:iata_code',
            'name' => 'required',
        ]);

        $aircraft->iata_code = $request->iata_code;
        $aircraft->name = $request->name;
        $aircraft->save();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
