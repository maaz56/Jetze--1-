<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModifyRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Log;

class ModifyRequestController extends Controller
{
    public function store(Request $request)
    {
        Log::info('ModifyRequestController@store called with data: ', $request->all());

        $validated = $request->validate([
            'booking_id' => 'required|integer',
            'user_id' => 'required|integer',
            'status' => 'string',
            'reason' => 'string|nullable',
            'message' => 'string|nullable',
        ]);

        // Create the initial ModifyRequest
        $modifyRequest = new ModifyRequest();
        $modifyRequest->booking_id = $validated['booking_id'];
        $modifyRequest->user_id = $validated['user_id'];
        $modifyRequest->status = $validated['status'] ?? 'pending';
        $modifyRequest->reason = $validated['reason'] ?? null;
        $modifyRequest->message = $validated['message'] ?? null;
        $user = User::find($validated['user_id']);
        // Initialize messages JSON with the initial message
        $initialMessage = $validated['message'] ?? null;
        if ($initialMessage) {
            $modifyRequest->messages = json_encode([
                [
                    'sender' => 'user',
                    'name' => $user ? $user->name : 'Unknown',
                    'sender_id' => $validated['user_id'],
                    'message' => $initialMessage,
                    'timestamp' => now()
                ]
            ]);
        } else {
            $modifyRequest->messages = '[]';
        }

        $modifyRequest->save();
        
        return response()->json($modifyRequest, 201);
    }


    public function addMessage(Request $request)
    {
        Log::info('ModifyRequestController@addMessage called with data: ', $request->all());
        $validated = $request->validate([
            'sender' => 'required|string', // 'user' or 'admin'
            'sender_id' => 'required|integer',
            'message' => 'required|string'
        ]);
        $modify = ModifyRequest::findOrFail($request->input('req_id'));

        $messages = $modify->messages ?? [];
        $messages = json_decode($messages, true) ?? [];
        $newMsg = [
            'sender' => $validated['sender'],
            'sender_id' => $validated['sender_id'],
            'message' => $validated['message'],
            'timestamp' => now()
        ];

        $messages[] = $newMsg;

        $modify->messages = json_encode($messages);
        $modify->save();

        return response()->json([
            'status' => true,
            'messages' => $messages
        ]);
    }

    public function index()
    {
        $requests = ModifyRequest::with(['booking', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info('ModifyRequestController@index retrieved requests: ', $requests->toArray());
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function show(Request $request)
    {
        Log::info('ModifyRequestController@show called with data: ', $request->all());
        $validated = $request->validate([
            'modify_request_id' => 'required|integer',
        ]);

    }

    public function fetchModifyRequestData(Request $request)
    {
        Log::info('ModifyRequestController@fetchModifyRequestData called with data: ', $request->all());

        $validated = $request->validate([
            'modify_request_id' => 'nullable|integer',
            'booking_id' => 'nullable|integer',
        ]);

        $query = ModifyRequest::with(['booking', 'user']);

        // Priority 1: modify_request_id
        if (!empty($validated['modify_request_id'])) {
            $query->where('id', $validated['modify_request_id']);
        }
        // Priority 2: booking_id
        elseif (!empty($validated['booking_id'])) {
            $query->where('booking_id', $validated['booking_id']);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either modify_request_id or booking_id is required.'
            ], 400);
        }

        $modifyRequest = $query->first();

        if (!$modifyRequest) {
            Log::warning("ModifyRequest not found for parameters:", $validated);
            return response()->json([
                'success' => false,
                'message' => 'Modify request not found'
            ], 200);
        }

        Log::info('ModifyRequest fetched: ', $modifyRequest->toArray());

        return response()->json([
            'success' => true,
            'data' => $modifyRequest
        ]);
    }

    public  function updateStatus(Request $request)
    {
        Log::info('ModifyRequestController@updateStatus called with data: ', $request->all());

        $validated = $request->validate([
            'modify_request_id' => 'required|integer',
            'status' => 'required|string',
        ]);

        $modifyRequest = ModifyRequest::find($validated['modify_request_id']);
        if (!$modifyRequest) {
            Log::warning("ModifyRequest not found for ID: " . $validated['modify_request_id']);
            return response()->json([
                'success' => false,
                'message' => 'Modify request not found'
            ], 404);
        }

        $modifyRequest->status = $validated['status'];
        $modifyRequest->save();

        Log::info('ModifyRequest status updated: ', $modifyRequest->toArray());

        return response()->json([
            'success' => true,
            'data' => $modifyRequest
        ]);
    }

}
