<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoImage;
use Illuminate\Http\Request;
use Log;
use Storage;

class PromoImageController extends Controller
{
    public function index()
    {
        $images = PromoImage::all();
        return response()->json($images);
    }

    // Store a newly created image in storage

    public function store(Request $request)
    {
        Log::info($request);
        $validatedData = $request->validate([
            'image_title' => 'required|string|max:255',
            'url' => 'nullable|image|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('public');
        }
        $imageUrl = Storage::url($imagePath);

        $image = PromoImage::create([
            'title' => $validatedData['image_title'],
            'url' => $imageUrl,
        ]);
        return response()->json($image, 201);
    }

    // Update the isHome field for a promo image
    public function updateIsHome(Request $request)
    {

        $validatedData = $request->validate([
            'isHome' => 'required|boolean',
        ]);

        $image = PromoImage::findOrFail($request->id);
        $image->is_home = $validatedData['isHome'];
        $image->save();

        return response()->json($image);
    }




    public function destroy(Request $request)
    {
        $image = PromoImage::find($request->id);
        $image->delete();
        // $request->$image->delete();
        // return response()->json(['message' => 'Image deleted successfully']);
    }

}
