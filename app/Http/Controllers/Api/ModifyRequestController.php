<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ModifyRequestMessageSent;
use App\Events\UserNotificationCreated;
use App\Models\FlightBookings;
use App\Models\ModifyRequest;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\DB;
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
        $modifyRequest->load('booking', 'user');
        $notification = [
            'type' => 'modify_request_submitted',
            'heading' => 'New Modify Request',
            'description' => "{$modifyRequest->user?->name} submitted a booking modification request.",
            'booking_id' => $modifyRequest->booking_id,
            'modify_request_id' => $modifyRequest->id,
            'url' => $this->modifyRequestChatUrl($modifyRequest),
        ];
        User::query()->where('role', 'admin')->each(
            fn (User $admin) => $this->notifyUser($admin, $notification)
        );
        
        return response()->json($modifyRequest, 201);
    }


    public function addMessage(Request $request)
    {
        $validated = $request->validate([
            'req_id' => 'required|integer',
            'message' => 'required|string|max:2000',
        ]);

        $sender = $request->user();
        $modify = ModifyRequest::with(['booking', 'user'])->findOrFail($validated['req_id']);
        $isRequestOwner = (int) $modify->user_id === (int) $sender->id;
        $canManageBookings = $sender->role === 'admin' || $sender->can('manage-bookings');
        abort_unless($isRequestOwner || $canManageBookings, 403, 'You cannot reply to this request.');

        $sentAt = now()->toISOString();
        $newMsg = DB::transaction(function () use ($modify, $sender, $canManageBookings, $validated, $sentAt) {
            $lockedRequest = ModifyRequest::lockForUpdate()->findOrFail($modify->id);
            $messages = json_decode($lockedRequest->messages ?: '[]', true) ?: [];
            $message = [
                'sender' => $canManageBookings ? 'admin' : 'user',
                'sender_id' => $sender->id,
                'name' => $sender->name,
                'message' => trim($validated['message']),
                'timestamp' => $sentAt,
                'created_at' => $sentAt,
            ];

            $messages[] = $message;
            $lockedRequest->messages = json_encode($messages);
            $lockedRequest->save();

            return $message;
        });

        event(new ModifyRequestMessageSent($modify->id, $newMsg));

        $notification = [
            'type' => 'modify_request_message',
            'heading' => 'New message on modify request',
            'description' => "{$sender->name} sent a message about a booking modification.",
            'booking_id' => $modify->booking_id,
            'modify_request_id' => $modify->id,
            'url' => $this->modifyRequestChatUrl($modify),
        ];

        if ($canManageBookings) {
            if ($modify->user) {
                $this->notifyUser($modify->user, $notification);
            }
        } else {
            User::query()->where('role', 'admin')->each(
                fn (User $admin) => $this->notifyUser($admin, $notification)
            );
        }

        return response()->json([
            'status' => true,
            'message' => $newMsg,
        ]);
    }

    public function openChat(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|integer',
        ]);

        $user = $request->user();
        $booking = FlightBookings::findOrFail($validated['booking_id']);
        $canManageBookings = $user->role === 'admin' || $user->can('manage-bookings');
        abort_unless($canManageBookings || (int) $booking->agent_id === (int) $user->id, 403, 'You cannot start a chat for this booking.');

        $modifyRequest = ModifyRequest::query()
            ->where('booking_id', $booking->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (!$modifyRequest) {
            $modifyRequest = ModifyRequest::create([
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'reason' => 'booking_chat',
                'messages' => '[]',
            ]);
            $modifyRequest->load(['booking', 'user']);

            $notification = [
                'type' => 'modify_request_submitted',
                'heading' => 'New booking chat',
                'description' => "{$user->name} started a chat about a booking.",
                'booking_id' => $modifyRequest->booking_id,
                'modify_request_id' => $modifyRequest->id,
                'url' => $this->modifyRequestChatUrl($modifyRequest),
            ];
            User::query()->where('role', 'admin')->each(
                fn (User $admin) => $this->notifyUser($admin, $notification)
            );
        }

        return response()->json(['data' => $modifyRequest->loadMissing(['booking', 'user'])]);
    }

    private function notifyUser(User $user, array $payload): void
    {
        $notification = new SystemNotification($payload);
        $user->notify($notification);

        event(new UserNotificationCreated($user->id, $payload + [
            'id' => $notification->id,
            'type' => SystemNotification::class,
            'read_at' => null,
            'created_at' => now()->toISOString(),
            'notification_type' => $payload['type'] ?? null,
        ]));
    }

    private function modifyRequestChatUrl(ModifyRequest $modifyRequest): string
    {
        $booking = $modifyRequest->booking;
        if (!$booking) {
            return '/admin/booking-requests?'.http_build_query([
                'inbox' => 'modify',
                'modify_request_id' => $modifyRequest->id,
            ]);
        }

        return '/customer-bookings-details?'.http_build_query(array_filter([
            'booking_id' => $booking->id,
            'pnr' => $booking->itinerary_ref ?: $booking->pnr,
            'agent_id' => $booking->agent_id,
            'booking_source' => $booking->booking_source,
            'flight_provider' => $booking->flight_provider,
            'flight_mode' => $booking->booking_mode ?: 'B2C',
            'modify_request_id' => $modifyRequest->id,
            'chat' => 1,
        ], fn ($value) => $value !== null && $value !== ''));
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
        $notification = [
            'type' => 'modify_request_'.$modifyRequest->status,
            'heading' => 'Modify Request '.ucfirst($modifyRequest->status),
            'description' => "Your booking modification request has been {$modifyRequest->status}.",
            'booking_id' => $modifyRequest->booking_id,
            'modify_request_id' => $modifyRequest->id,
            'url' => $this->modifyRequestChatUrl($modifyRequest),
        ];
        if ($modifyRequest->user) {
            $this->notifyUser($modifyRequest->user, $notification);
        }

        Log::info('ModifyRequest status updated: ', $modifyRequest->toArray());

        return response()->json([
            'success' => true,
            'data' => $modifyRequest
        ]);
    }

}
