<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentCharge;
use App\Models\Country;
use App\Models\Visa;
use App\Models\VisaHeaderImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VisaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $visas = Visa::get();
        return $visas;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }
    //testing

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'header_image' => 'required',
            'title' => 'required',
            'description' => 'required',
            'currency' => 'required',
            'starting_price' => 'required',
            'country' => 'required',
        ]);

        $country = Country::where('name', $request->country)->first();
        $visa = new Visa();
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
        $country = Country::where('name', $request->country)->first();

        $visa = Visa::where('id', $request->visa_id)->first();

        if ($request->header_image) {
            $visa->header_image = $request->header_image['url'];
        }
        $visa->title = $request->title;
        $visa->country_flag = $country->flag;
        $visa->country_name = $country->name;
        $visa->starting_price = $request->starting_price;
        $visa->description = $request->description;
        $visa->currency = $request->currency;
        $visa->save();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {   
        Log::info($request);
        $visa = Visa::where('id', $request->id)->first();
        $charges = AgentCharge::where('id', $request->id)->first();
        if($visa) {
            $visa->delete();
        }
        if($charges) {
            $charges->delete();
        }
    }

    public function getVisaHeaderImages()
    {
        $images = VisaHeaderImage::get();
        return $images;
    }

    public function saveVisaHeaderImages(Request $request)
    {
        foreach ($request->headerImages as $image) {
            $visa = new VisaHeaderImage();
            $visa->name = $image['name'];
            $visa->url = $image['url'];
            $visa->save();
        }
    }

    public function deleteVisaHeaderImages(Request $request, FileUploadController $fileUploadController)
    {
        VisaHeaderImage::where('name', $request->file_name)->delete();
        $fileUploadController->destroy($request);
    }
}
