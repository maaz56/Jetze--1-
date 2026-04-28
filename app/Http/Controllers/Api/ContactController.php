<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:40',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|min:10|max:5000',
            'website' => 'nullable|string|max:255',
            'form_started_at' => 'required|integer',
        ]);

        // Honeypot: real users never fill this hidden field.
        if (!empty($validated['website'])) {
            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully.',
            ]);
        }

        // Basic timing check to reduce scripted submissions.
        $startedAt = Carbon::createFromTimestampMs($validated['form_started_at']);
        if ($startedAt->diffInSeconds(now()) < 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please wait a moment and try again.',
            ], 422);
        }

        $adminEmail = env('ADMIN_EMAIL')
            ?: User::where('role', 'admin')->value('email')
            ?: config('mail.from.address');

        if (empty($adminEmail)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Contact mailbox is not configured.',
            ], 500);
        }

        Mail::to($adminEmail)->send(new ContactMessageMail($validated));

        return response()->json([
            'status' => 'success',
            'message' => 'Message sent successfully.',
        ]);
    }
}
