<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Nnjeim\World\World;
use Nnjeim\World\WorldHelper;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
    {
        if ($request->searchQuery) {
            $searchQuery = $request->searchQuery;

            // First, try to match code3
            $items = Country::where('code3',   $searchQuery)
            ->paginate(Constants::$PAGE_LIMIT);

            // If no results, try to match name
            if ($items->isEmpty()) {
            $items = Country::where('name', 'LIKE', '%' . $searchQuery . '%')
                ->paginate(Constants::$PAGE_LIMIT);
            }

            return $items;
        }
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
        $validator = $request->validate([
            'code' => 'required',
            'name' => 'requires'
        ]);

        $country = new Country();
        $country->code = $request->code;
        $country->name = $request->name;
        $country->save();
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
        $validator = $request->validate([
            'code' => 'required',
            'name' => 'requires'
        ]);

        $country = Country::where('id', $id)->first();
        $country->code = $request->code;
        $country->name = $request->name;
        $country->save();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $country = Country::where('id', $id)->first();
        $country->delete();
    }
}
