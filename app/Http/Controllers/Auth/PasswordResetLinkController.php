<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPassword;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;


class PasswordResetLinkController extends Controller
{
    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => [__('We couldn’t find a user with that email address.')],
            ]);
        }

        // Generate a token for password reset
        $token = app('auth.password.broker')->createToken($user);

        // Send custom reset password email
        Mail::to($user->email)->send(new ResetPassword($token, $user->email));

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'Password reset link sent successfully.'
            ]
        ]);
    }
}
