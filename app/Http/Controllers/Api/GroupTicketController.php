<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupTicket;
use Illuminate\Http\Request;

class GroupTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $groupTickets = GroupTicket::get();
        return $groupTickets;
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
            'airline_id' => 'required',
            'departure_date' => 'required',
            'price' => 'required',
        ]);

        GroupTicket::create($request->all());
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
        $request->validate([
            'airline_id' => 'required',
            'departure_date' => 'required',
            'price' => 'required',
        ]);

        $groupTicket = GroupTicket::findOrFail($request->id);
        $groupTicket->update($request->all());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id = null, Request $request)
    {
        $groupTicket = GroupTicket::findOrFail($request->id);
        $groupTicket->delete();
    }
}
