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
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string'],
            'mode' => ['nullable', 'in:check,register,password'],
            'resume_registration' => ['nullable', 'boolean'],
        ]);

        $email = strtolower(trim($validated['email']));
        $mode = $validated['mode'] ?? null;
        $password = $validated['password'] ?? null;
        $resumeRegistration = (bool) ($validated['resume_registration'] ?? false);
        $emailKey = hash('sha256', $email);
        $isDevelopmentOtpBypass = !config('app.otp_enabled')
            && strtoupper((string) config('app.vite_mode')) === 'LOCAL';

        $user = User::select(
            'id',
            'email',
            'password',
            'role',
            'name'
        )
            ->where('email', $email)
            ->first();

        // This fast check lets the client choose the next screen before the
        // synchronous email delivery request starts.
        if ($mode === 'check') {
            return response()->json([
                'success' => true,
                'account_exists' => (bool) $user,
                'requires_password' => !$user,
            ]);
        }

        // A new email must set its password before an OTP is issued.
        if (!$user && $mode !== 'register' && !$resumeRegistration) {
            return response()->json([
                'success' => true,
                'account_exists' => false,
                'requires_password' => true,
            ]);
        }

        if ($mode === 'password') {
            if (!$user || !filled($password) || !Hash::check($password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => [
                        'status' => 'error',
                        'description' => 'Invalid email or password.',
                    ],
                ], 401);
            }

            Auth::login($user);
            $token = $user->createToken('user_token');

            return response()->json([
                'success' => true,
                'skip_otp' => true,
                'message' => [
                    'status' => 'success',
                    'description' => 'Logged in successfully.',
                ],
                'token' => $token,
            ]);
        }

        if (!$user) {
            if ($resumeRegistration && !Cache::has('login_registration_password_' . $emailKey)) {
                return response()->json([
                    'success' => true,
                    'account_exists' => false,
                    'requires_password' => true,
                ]);
            }

            if ($mode === 'register') {
                $request->validate([
                    'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/\d/', 'regex:/[^A-Za-z0-9]/'],
                ]);
                Cache::put('login_registration_password_' . $emailKey, Hash::make($password), 300);
            }
        }

        if ($isDevelopmentOtpBypass) {
            return response()->json([
                'success' => true,
                'message' => [
                    'status' => 'success',
                    'description' => 'Development mode: OTP email delivery is skipped.',
                ],
                'account_exists' => (bool) $user,
            ]);
        }

        // Keep the OTP in cache for five minutes after a password is set for a
        // new account, or immediately for an existing account.
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpCacheKey = 'login_otp_' . $emailKey;
        Cache::put($otpCacheKey, $otp, 300);

        Mail::to($email)->send(new OTPMail($email, $otp, $user?->name ?? Str::before($email, '@')));

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'OTP has been sent to your email.'
            ],
            'account_exists' => (bool) $user,
        ]);
    }

    /**
     * Verify OTP and complete login.
     */
    public function verifyLoginOtp(Request $request)
    {
        $isDevelopmentOtpBypass = !config('app.otp_enabled')
            && strtoupper((string) config('app.vite_mode')) === 'LOCAL';

        $request->validate([
            'email' => 'required|email',
            'otp' => $isDevelopmentOtpBypass
                ? 'required|string'
                : 'required|string|size:6',
        ]);

        $email = strtolower(trim($request->email));
        $storedOtp = $isDevelopmentOtpBypass
            ? null
            : Cache::get('login_otp_' . hash('sha256', $email));

        if (!$isDevelopmentOtpBypass && (!$storedOtp || $storedOtp !== $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'Invalid or expired OTP.'
                ]
            ], 401);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $name = Str::before($email, '@');
            $pendingPassword = Cache::get('login_registration_password_' . hash('sha256', $email));

            if (!$pendingPassword) {
                return response()->json([
                    'success' => false,
                    'message' => [
                        'status' => 'error',
                        'description' => 'Please create a password before verifying your email.',
                    ],
                ], 422);
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $pendingPassword,
                    'role' => 'customer',
                    'is_approved' => true,
                    'is_formFilled' => true,
                    'email_verified_at' => now(),
                ],
            );

            \App\Models\Customer::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $name,
                    'email' => $email,
                    'phone' => null,
                    'address' => null,
                ],
            );
        }

        Cache::forget('login_otp_' . hash('sha256', $email));
        Cache::forget('login_registration_password_' . hash('sha256', $email));

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
