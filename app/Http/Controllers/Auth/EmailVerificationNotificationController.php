<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Log;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */

    

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Log::info('Received request to resend email verification link for user ID: ' . $request->user()->id);
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended('/dashboard');
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );
        Log::info('Generated verification URL: ' . $verificationUrl);
        Mail::to($user->email)->send(new EmailVerification(
            $verificationUrl,
            (string) ($user->name ?? ''),
            (string) ($user->email ?? ''),
            null,
            'Jetze'
        ));

        return response()->json(['status' => 'verification-link-sent']);
    }
}
