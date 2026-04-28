<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiEmailVerificationController;
use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use Auth;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VerifyEmailController extends Controller
{

    // public function __invoke(EmailVerificationRequest $request): RedirectResponse
    // {
    //     $user = $request->user();

    //     if ($user->hasVerifiedEmail()) {
    //         if ($user->role == 'user') {
    //             return redirect()->intended(
    //                 config('app.frontend_url') . '?verified=1'
    //             );
    //         } else if ($user->role == 'agent') {
    //             return redirect()->intended(
    //                 config('app.frontend_url') . '/agent/dashboard?verified=1'
    //             );
    //         }
    //     }

    //     if ($user->markEmailAsVerified()) {
    //         event(new Verified($user));
    //     }

    //     return redirect()->intended(
    //         config('app.frontend_url') . '?verified=1'
    //     );
    // }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {

        $user = $request->user();

        // Check if the email is already verified
        if ($user->hasVerifiedEmail()) {
            return $this->redirectBasedOnRole($user);
        }

        // Mark the email as verified and log the event
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            Log::info("User email verified: User ID {$user->id}");

            $loginUrl = rtrim((string) config('app.frontend_url'), '/');
            $userName = trim((string) ($user->name ?? ''));
            if ($userName === '') {
                $userName = 'Valued User';
            }

            Mail::to($user->email)->send(new WelcomeMail($userName, $loginUrl));
        }

        // Redirect based on user role after verification
        return $this->redirectBasedOnRole($user);
    }

    // public function verify(EmailVerificationRequest $request)
    // {
    //     if ($request->user()->hasVerifiedEmail()) {
    //         return response()->json(['message' => 'Email already verified.'], 200);
    //     }

    //     if ($request->user()->markEmailAsVerified()) {
    //         event(new Verified($request->user()));
    //     }

    //     return response()->json(['message' => 'Email successfully verified.']);
    // }

    /**
     * Redirect the user based on their role.
     */
    protected function redirectBasedOnRole($user): RedirectResponse
    {
        $baseUrl = config('app.frontend_url');

        switch ($user->role) {
            case 'user':
            case 'customer':
                return redirect()->intended("{$baseUrl}?verified=1");
            case 'agent':
                return redirect()->intended("{$baseUrl}/email-success-message?verified=1");
            default:
                // Fallback for unknown roles
                return redirect()->intended("{$baseUrl}/unknown-role?verified=1");
        }
    }
}
    
