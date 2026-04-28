<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OfflineBookingCreatedMail;
use App\Models\OfflineBooking;
use App\Models\OfflineBookingTravellers;
use Illuminate\Http\Request;
use Log;
use Mail;

class OfflineBookingController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Incoming Flight Request:', $request->all());

        $validated = $request->validate([
            'agentId' => 'nullable|integer',
            'flightType' => 'required|string',
            'classType' => 'nullable|string',
            'adult' => 'required|integer',
            'child' => 'required|integer',
            'infant' => 'required|integer',

            // Either full "route" OR separate fields
            'route' => 'nullable',
            'origin' => 'nullable|string|max:10',
            'destination' => 'nullable|string|max:10',
            'dateRange.start' => 'nullable|date',
            'dateRange.end' => 'nullable|date',

            'travellers.*.type' => 'required|string',
            'travellers.*.title' => 'nullable|string',
            'travellers.*.firstName' => 'required|string',
            'travellers.*.lastName' => 'required|string',
            'travellers.*.nationality' => 'nullable|string|max:5',
            'travellers.*.documentType' => 'nullable|string',
            'travellers.*.documentNo' => 'nullable|string',
            'travellers.*.expiryDate' => 'nullable|date',
            'travellers.*.issueCountry' => 'nullable|string|max:5',
            'travellers.*.dob' => 'nullable|date',
            'travellers.*.gender' => 'nullable|string|max:1',
            'amount' => 'nullable',
            'bookingPnr' => 'required|string|max:10',

        ]);

        // ✅ Decide how to handle route
        $routeData = $validated['route'] ?? null;

        if (!$routeData && isset($validated['origin'], $validated['destination'])) {
            // Convert separate fields into array
            $routeData = [
                'origin' => $validated['origin'],
                'destination' => $validated['destination'],
                'dateRange' => [
                    'start' => $validated['dateRange']['start'] ?? null,
                    'end' => $validated['dateRange']['end'] ?? null,
                ],
            ];
        }

        $booking = OfflineBooking::create([
            'agent_id' => $validated['agentId'],
            'flight_type' => $validated['flightType'],
            'class_type' => $validated['classType'] ?? null,
            'adult' => $validated['adult'],
            'child' => $validated['child'],
            'infant' => $validated['infant'],
            'route' => json_encode($routeData),
            'amount' => $validated['amount'] ?? null,
            'booking_pnr' => $validated['bookingPnr'] ?? null,
        ]);

        // ✅ Save travellers
        foreach ($validated['travellers'] as $traveller) {
            OfflineBookingTravellers::create([
                'offline_booking_id' => $booking->id,
                'type' => $traveller['classType'] ?? 'ADT',
                'title' => $traveller['title'] ?? null,
                'first_name' => $traveller['firstName'],
                'last_name' => $traveller['lastName'],
                'nationality' => $traveller['nationality'] ?? null,
                'document_type' => $traveller['documentType'] ?? null,
                'document_no' => $traveller['documentNo'] ?? null,
                'expiry_date' => $traveller['expiryDate'] ?? null,
                'issue_country' => $traveller['issueCountry'] ?? null,
                'dob' => $traveller['dob'] ?? null,
                'gender' => $traveller['gender'] ?? null,

            ]);
        }

        $booking = OfflineBooking::with('user', 'travellers')->find($booking->id);
        
        Mail::to($booking->user->email)->send(new OfflineBookingCreatedMail($booking));

        return response()->json([
            'message' => 'Flight request created successfully',
            'booking' => $booking->load('travellers'),
        ], 201);
    }



   public function index()
    {
        $user = auth()->user();

        if ($user && $user->role === 'admin') {
            // Admin: return all bookings
            $bookings = OfflineBooking::with(['travellers', 'user.agentData'])->latest()->get();
        } else {
            // Agent: return only their bookings
            $bookings = OfflineBooking::with(['travellers', 'user.agentData'])
            ->where('agent_id', $user->id)
            ->latest()
            ->get();
        }

        return response()->json([
            'data' => $bookings
        ]);
    }

    public function getFlightBookingDetails(Request $request)
    {
        $bookingId = $request->bookingId;

        if (!$bookingId) {
            return response()->json(['error' => 'Booking ID is required'], 400);
        }

        $booking = OfflineBooking::with(['travellers', 'user.agentData'])->find($bookingId);
        Log::info($booking);
        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        return response()->json([
            'data' => $booking
        ]);
    }

    public function destroy(Request $request)
    {
        $bookingId = $request->id;

        if (!$bookingId) {
            return response()->json(['error' => 'Booking ID is required'], 400);
        }

        $booking = OfflineBooking::find($bookingId);



        // Delete associated travellers first
        OfflineBookingTravellers::where('offline_booking_id', $bookingId)->delete();

        // Then delete the booking
        $booking->delete();

        return response()->json(['message' => 'Booking deleted successfully']);
    }

    public function update(Request $request)
    {
        Log::info('Incoming Update Flight Request:', $request->all());

        $validated = $request->validate([
            'id' => 'required|integer|exists:offline_bookings,id',
            'agentId' => 'nullable|integer',
            'flightType' => 'required|string',
            'classType' => 'nullable|string',
            'adult' => 'required|integer',
            'child' => 'required|integer',
            'infant' => 'required|integer',
            'amount' => 'nullable|numeric|min:0',
            'bookingPnr' => 'required|string|max:10',
            // Either full "route" OR separate fields
            'route' => 'nullable',
            'origin' => 'nullable|string|max:10',
            'destination' => 'nullable|string|max:10',
            'dateRange.start' => 'nullable|date',
            'dateRange.end' => 'nullable|date',

            'travellers' => 'required|array|min:1',
            'travellers.*.id' => 'nullable|integer|exists:offline_booking_travellers,id',
            'travellers.*.type' => 'required|string',
            'travellers.*.title' => 'nullable|string',
            'travellers.*.firstName' => 'required|string',
            'travellers.*.lastName' => 'required|string',
            'travellers.*.nationality' => 'nullable|string|max:5',
            'travellers.*.documentType' => 'nullable|string',
            'travellers.*.documentNo' => 'nullable|string',
            'travellers.*.expiryDate' => 'nullable|date',
            'travellers.*.issueCountry' => 'nullable|string|max:5',
            'travellers.*.dob' => 'nullable|date',
            'travellers.*.gender' => 'nullable|string|max:1',
        ]);

        // ✅ Find booking
        $booking = OfflineBooking::findOrFail($validated['id']);

        // ✅ Decide how to handle route
        $routeData = $validated['route'] ?? null;
        if (!$routeData && isset($validated['origin'], $validated['destination'])) {
            $routeData = [
                'origin' => $validated['origin'],
                'destination' => $validated['destination'],
                'dateRange' => [
                    'start' => $validated['dateRange']['start'] ?? null,
                    'end' => $validated['dateRange']['end'] ?? null,
                ],
            ];
        }

        // ✅ Update booking
        $booking->update([
            'agent_id' => $validated['agentId'],
            'flight_type' => $validated['flightType'],
            'class_type' => $validated['classType'] ?? null,
            'adult' => $validated['adult'],
            'child' => $validated['child'],
            'infant' => $validated['infant'],
            'route' => json_encode($routeData),
            'amount' => $validated['amount'] ?? null,
            'booking_pnr' => $validated['bookingPnr'] ?? null,
        ]);

        // ✅ Handle travellers
        $travellerIds = [];

        foreach ($validated['travellers'] as $traveller) {
            if (!empty($traveller['id'])) {
                // Update existing traveller
                $travellerModel = OfflineBookingTravellers::where('offline_booking_id', $booking->id)
                    ->where('id', $traveller['id'])
                    ->first();

                if ($travellerModel) {
                    $travellerModel->update([
                        'type' => $traveller['type'],
                        'title' => $traveller['title'] ?? null,
                        'first_name' => $traveller['firstName'],
                        'last_name' => $traveller['lastName'],
                        'nationality' => $traveller['nationality'] ?? null,
                        'document_type' => $traveller['documentType'] ?? null,
                        'document_no' => $traveller['documentNo'] ?? null,
                        'expiry_date' => $traveller['expiryDate'] ?? null,
                        'issue_country' => $traveller['issueCountry'] ?? null,
                        'dob' => $traveller['dob'] ?? null,
                        'gender' => $traveller['gender'] ?? null,
                    ]);
                    $travellerIds[] = $travellerModel->id;
                }
            } else {
                // Create new traveller
                $newTraveller = OfflineBookingTravellers::create([
                    'offline_booking_id' => $booking->id,
                    'type' => $traveller['type'],
                    'title' => $traveller['title'] ?? null,
                    'first_name' => $traveller['firstName'],
                    'last_name' => $traveller['lastName'],
                    'nationality' => $traveller['nationality'] ?? null,
                    'document_type' => $traveller['documentType'] ?? null,
                    'document_no' => $traveller['documentNo'] ?? null,
                    'expiry_date' => $traveller['expiryDate'] ?? null,
                    'issue_country' => $traveller['issueCountry'] ?? null,
                    'dob' => $traveller['dob'] ?? null,
                    'gender' => $traveller['gender'] ?? null,
                ]);
                $travellerIds[] = $newTraveller->id;
            }
        }

        // ✅ Optionally delete removed travellers
        OfflineBookingTravellers::where('offline_booking_id', $booking->id)
            ->whereNotIn('id', $travellerIds)
            ->delete();

        return response()->json([
            'message' => 'Booking updated successfully',
            'booking' => $booking->load('travellers'),
        ]);
    }

    public function sendEmail(Request $request)
    {
        Log::info('Incoming Send Email Request:', $request->all());

        $validated = $request->validate([
            'booking_id' => 'required|integer|exists:offline_bookings,id',
            'email' => 'required|email',
        ]);

        // ✅ Find booking
        $booking = OfflineBooking::with('user', 'travellers')->findOrFail($validated['booking_id']);

        // Send email
        Mail::to($validated['email'])->send(new OfflineBookingCreatedMail($booking));

        return response()->json([
            'message' => 'Email sent successfully',
        ]);
    }

}
