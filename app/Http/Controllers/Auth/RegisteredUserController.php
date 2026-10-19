<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerification;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules;
use Log;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {

        // Early role check
        $role = $request->input('role');

        if ($role !== 'customer') {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'Invalid role specified.',
                ]
            ], 422);
        }

        DB::beginTransaction();

        try {
            // ===================================================
            // 🧩 VALIDATION + USER CREATION
            // ===================================================
            if ($role === 'customer') {
                $validated = $request->validate([
                    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
                    'password' => ['required', 'confirmed', Rules\Password::defaults()],
                    'phone' => ['nullable', 'string', 'max:255'],
                    'role' => ['required', 'in:customer'],
                    'name' => 'required|string|max:255',
                    // 'lastName' => 'nullable|string|max:255',
                    'mobile' => 'nullable|string|max:15',
                    'address' => 'nullable|string',
                    'preferred_currency' => 'nullable',
                    'company_name' => 'nullable|string|max:255',
                ]);
            
                $user = User::create([
                    'name' => trim(($validated['name'])),
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'is_approved' => true,
                    'is_formFilled' => true,
                    'role' => $role,
                    'email_verified_at' => now(),

                ]);

                Customer::create([
                    'user_id' => $user->id,
                    'name' => $validated['name'],
                    // 'last_name' => $validated['lastName'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'preferred_currency' => $validated['preferred_currency'] ?? 'PKR',
                    'company_name' => $validated['company_name'] ?? null,
                ]);
            }

            // ===================================================
            // 📧 SEND VERIFICATION EMAIL   
            // ===================================================
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
                ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
            );
           Mail::to($user->email)->send(new EmailVerification(
                $verificationUrl,
                (string) ($user->name ?? ''),
                (string) ($user->email ?? ''),
                null,
                'Jetze'
            ));

            // ===================================================
            // ✅ LOGIN + RESPONSE
            // ===================================================
            Auth::login($user);
            DB::commit();
            
            return response()->json([
                'success' => true,
                'user' => $user,
                'message' => [
                    'status' => 'success',
                    'description' => 'Registered Successfully.'
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'Registration failed.',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }



}
