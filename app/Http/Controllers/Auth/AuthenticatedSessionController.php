<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use App\Mail\OTPMail;
use App\Models\AgentData;
use Laravel\Socialite\Facades\Socialite;

class AuthenticatedSessionController extends Controller
{

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'Logged in Successfully.'
            ],
            'data' => [
                'user' => auth()->guard('web')->user(),
            ]
        ], 200);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
       Auth::guard('web')->logout(); // Use the 'web' guard for session-based logout

    $request->session()->invalidate(); // Invalidate the session
    $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'Logged out Successfully.'
            ]
        ], 200);
    }

    /**
     * Request OTP for login.
     */
    public function requestLoginOtp(Request $request)
    {
        // If OTP disabled, just login directly
        if (!config('app.otp_enabled')) {
            $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => [
                        'status' => 'error',
                        'description' => 'Invalid Email or Password!.'
                    ]
                ], 401);
            }

            Auth::login($user);
            $token = $user->createToken("debug_token");
            return ['token' => $token];

        }

        // Otherwise normal OTP flow
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::select(
            'id',
            'email',
            'password',
            'role',
            'name',
            'sms_otp_disabled',
            'sms_otp_disabled_browser_id'
        )
            ->where('email', $validated['email'])
            ->first();

        $hashedPassword = $user?->password;

        if (!$user || !Hash::check($validated['password'], $hashedPassword)) {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'Invalid Email or Password!.'
                ]
            ], 401);
        }

        $browserId = (string) ($request->header('X-Browser-Id') ?: $request->input('browser_id', ''));
        $isCustomerRole = in_array($user->role, ['customer', 'user'], true);
        $isBrowserOtpDisabled = $isCustomerRole
            && (bool) $user->sms_otp_disabled
            && !empty($browserId)
            && hash_equals((string) $user->sms_otp_disabled_browser_id, $browserId);

        if ($isBrowserOtpDisabled) {
            $token = $user->createToken("user_token");

            return response()->json([
                'success' => true,
                'skip_otp' => true,
                'message' => [
                    'status' => 'success',
                    'description' => 'Logged in successfully.'
                ],
                'token' => $token,
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("login_otp_{$user->id}", $otp, 300);

        Mail::to($user->email)->send(new OTPMail($user, $otp, $user->name));

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'OTP has been sent to your email.'
            ]
        ]);
    }

    /**
     * Verify OTP and complete login.
     */
    public function verifyLoginOtp(Request $request)
    {
        // If OTP disabled, just login directly
        if (!config('app.otp_enabled')) {
            $user = User::with('customer')->where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => [
                        'status' => 'error',
                        'description' => 'User not found.'
                    ]
                ], 404);
            }

          
            //Auth::login($user);
            $token = $user->createToken("user_token");
            return ['token' => $token];
            //$request->session()->regenerate();


            // return response()->json([
            //     'success' => true,
            //     'message' => [
            //         'status' => 'success',
            //         'description' => 'Logged in Successfully (OTP bypassed).'
            //     ],
            //     'data' => [
            //         'user' => $user,
            //     ]
            // ]);
        }

        // Normal OTP flow
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'User not found.'
                ]
            ], 404);
        }

        $storedOtp = Cache::get('login_otp_' . $user->id);

        if (!$storedOtp || $storedOtp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'Invalid or expired OTP.'
                ]
            ], 401);
        }

        Cache::forget('login_otp_' . $user->id);

        Auth::login($user);
         $token = $user->createToken("user_token");
         return ['token' => $token];
        // $request->session()->regenerate();

        // return response()->json([
        //     'success' => true,
        //     'message' => [
        //         'status' => 'success',
        //         'description' => 'Logged in Successfully.'
        //     ],
        //     'data' => [
        //         'user' => $user,

        //     ]
        // ]);
    }


}
