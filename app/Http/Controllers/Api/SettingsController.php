<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function saveAgentProfile(Request $request)
    {
        $user = User::where('id', Auth::user()->id)->first();

        if ($request->hasFile('image')) {
            // Delete the old image if it exists
            if ($user->profile_image_name) {
                Storage::disk('public')->delete('images/' . $user->profile_image_name);
            }

            // Store the new image
            $imagePath = $request->file('image')->store('images', 'public');
            $imageName = basename($imagePath);

            $imageUrl = Storage::url($imagePath);

            $user->profile_image_name = $imageName;
            $user->profile_image_url = url($imageUrl);
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'Profile image saved successfully.',
            ],
        ], 200);
    }
}
