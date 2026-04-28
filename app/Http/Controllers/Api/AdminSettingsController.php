<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use Illuminate\Http\Request;
use Log;

class AdminSettingsController extends Controller
{
    public function index()
    {
        return response()->json([]);

    }

    public function getBookiongStatusSettings(Request $request){
        $adminSetting = AdminSetting::first(); // Get the first row

        return response()->json([
            'bookingStatus' => $adminSetting ? $adminSetting->bookingStatus : 0, // Default to 0 if no record exists
        ]);
    }

    public function updateBooking(Request $request)
    {
        $bookingStatus = $request->input('booking_status'); // Get the value from the request

        $adminSetting = AdminSetting::first(); // Get the first row

        if ($adminSetting) {
            $adminSetting->update([
                'bookingStatus' => $bookingStatus,
            ]);
        } else {
            // If no record exists, create a new one (optional)
            $adminSetting = AdminSetting::create([
                'bookingStatus' => $bookingStatus,
            ]);
        }

        return response()->json([
            'message' => 'Booking status updated successfully',
            'status' => $adminSetting->bookingStatus,
        ]);

    }

}
