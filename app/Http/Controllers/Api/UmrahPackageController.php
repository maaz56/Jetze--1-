<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UmrahHeaderImage;
use App\Models\UmrahPackage;
use Illuminate\Http\Request;

class UmrahPackageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $umrahPackages = UmrahPackage::get();
        return $umrahPackages;
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
            'description' => 'required',
            'currency' => 'required',
        ]);

        $umrahPackage = new UmrahPackage();
        $umrahPackage->header_image = $request->header_image['url'];
        $umrahPackage->title = $request->title;
        $umrahPackage->starting_price = $request->starting_price;
        $umrahPackage->description = $request->description;
        $umrahPackage->currency = $request->currency;
        $umrahPackage->save();
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
        $umrahPackage = UmrahPackage::where('id', $request->umrah_package_id)->first();
        if ($umrahPackage) {
            if ($request->header_image) {
                $umrahPackage->header_image = $request->header_image['url'];
            }
            $umrahPackage->title = $request->title;
            $umrahPackage->starting_price = $request->starting_price;
            $umrahPackage->description = $request->description;
            $umrahPackage->currency = $request->currency;
            $umrahPackage->save();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $umrahPackage = UmrahPackage::where('id', $request->id)->first();

        $umrahPackage->delete();
    }

    public function getUmrahHeaderImages()
    {
        $images = UmrahHeaderImage::get();
        return $images;
    }

    public function saveUmrahHeaderImages(Request $request)
    {
        foreach ($request->headerImages as $image) {
            $visa = new UmrahHeaderImage();
            $visa->name = $image['name'];
            $visa->url = $image['url'];
            $visa->save();
        }
    }

    public function deleteUmrahHeaderImages(Request $request, FileUploadController $fileUploadController)
    {
        UmrahHeaderImage::where('name', $request->file_name)->delete();
        $fileUploadController->destroy($request);
    }
}
