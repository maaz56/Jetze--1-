<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\HolidayHeaderImage;
use App\Models\HolidayPackage;
use Illuminate\Http\Request;
use Log;

class HolidayPackageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $holidays = HolidayPackage::get();
        return $holidays;
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
            'header_image' => 'required',
            'title' => 'required',
            'description' => 'required'
        ]);

        $country = Country::where('name', $request->country)->first();

        $visa = new HolidayPackage();
        $visa->header_image = $request->header_image['url'];
        $visa->title = $request->title;
        $visa->country_flag = $country->flag;
        $visa->country_name = $country->name;
        $visa->starting_price = $request->starting_price;
        $visa->description = $request->description;
        $visa->currency = $request->currency;
        $visa->save();
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
        Log::info($request->all());
        $country = Country::where('name', $request->country)->first();

        $holiday = HolidayPackage::where('id', $request->holiday_id)->first();

        if ($request->header_image) {
            $holiday->header_image = $request->header_image['url'];
        }
        $holiday->title = $request->title;
        $holiday->country_flag = $country->flag;
        $holiday->country_name = $country->name;
        $holiday->starting_price = $request->starting_price;
        $holiday->description = $request->description;
        $holiday->currency = $request->currency;
        $holiday->save();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $visa = HolidayPackage::where('id', $request->id)->first();

        $visa->delete();
    }

    public function getHolidayHeaderImages()
    {
        $images = HolidayHeaderImage::get();
        return $images;
    }

    public function saveHolidayHeaderImages(Request $request)
    {
        foreach ($request->headerImages as $image) {
            $visa = new HolidayHeaderImage();
            $visa->name = $image['name'];
            $visa->url = $image['url'];
            $visa->save();
        }
    }

    public function deleteHolidayHeaderImages(Request $request, FileUploadController $fileUploadController)
    {
        HolidayHeaderImage::where('name', $request->file_name)->delete();
        $fileUploadController->destroy($request);
    }
}
