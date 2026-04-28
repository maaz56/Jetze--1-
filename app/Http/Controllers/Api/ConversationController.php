<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
      public function index(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|integer',
        ]);

        $messages = Conversation::where('conversation_id', $request->conversation_id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages
        ]);
    }

    /**
     * Store a newly created message
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer',
            'booking_id'      => 'required|integer',
            'sender_id'       => 'required|integer',
            'receiver_id'     => 'required|integer',
            'message'         => 'required|string',
        ]);

        $message = Conversation::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Conversation message saved',
            'data'    => $message
        ], 201);
    }

    /**
     * Show a single message
     */
    public function show($id)
    {
        $message = Conversation::find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $message
        ]);
    }

    /**
     * Update a conversation message
     */
    public function update(Request $request, $id)
    {
        $message = Conversation::find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found'
            ], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $message->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Message updated successfully',
            'data'    => $message
        ]);
    }

    /**
     * Delete a conversation message
     */
    public function destroy($id)
    {
        $message = Conversation::find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found'
            ], 404);
        }

        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully'
        ]);
    }
}
