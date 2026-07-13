<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerification;
use App\Models\AgentData;
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
use Storage;

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

        if (!in_array($role, ['agent', 'customer'])) {
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
            if ($role === 'agent') {
                $validated = $request->validate([
                    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
                    'password' => ['required', 'confirmed', Rules\Password::defaults()],
                    'phone' => ['required', 'string', 'max:255'],
                    'role' => ['required', 'in:agent'],
                    'name' => 'required|string|max:255',
                    'lastName' => 'nullable|string|max:255',
                    'company_name' => 'required|string|max:255',
                    'govt_number' => 'nullable|string|max:255',
                    'mobile' => 'required|string|max:15',
                    'ceo_name' => 'nullable|string|max:255',
                    'ceo_contact' => 'nullable|string|max:15',
                    'ceo_email' => 'nullable|email|max:255',
                    'company_email' => 'nullable|email|max:255',
                    'address' => 'nullable|string',
                    'logo' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:2048',
                    'license' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:2048',
                    'e_id.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:2048',
                ]);

                $user = User::create([
                    'email' => $validated['email'],
                    'name' => $validated['name'] ?? '',
                    'password' => Hash::make($validated['password']),
                    'role' => $role,
                    'is_approved' => 0,
                    'is_formFilled' => 1,
                ]);

                // ===================================================
                // 📂 HANDLE FILE UPLOADS
                // ===================================================
                $logoPath = $licencePath = $eidPaths = null;

                if ($request->hasFile('logo')) {
                    $path = $request->file('logo')->storeAs('public/uploads', uniqid() . '_' . $request->file('logo')->getClientOriginalName());
                    $logoPath = Storage::url($path);
                }

                if ($request->hasFile('license')) {
                    $path = $request->file('license')->storeAs('public/uploads', uniqid() . '_' . $request->file('license')->getClientOriginalName());
                    $licencePath = Storage::url($path);
                }

                if ($request->hasFile('e_id')) {
                    $eidPaths = [];
                    foreach ($request->file('e_id') as $file) {
                        $path = $file->storeAs('public/uploads', uniqid() . '_' . $file->getClientOriginalName());
                        $eidPaths[] = Storage::url($path);
                    }
                    $eidPaths = json_encode($eidPaths);
                }

                // ===================================================
                // 🆔 CREATE AGENT RECORD
                // ===================================================
                $lastId = AgentData::max('id') ?? 0;
                $nextNumber = str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
                $year = date('y');
                $agentUid = "AGN-" . $year . $nextNumber;

                AgentData::create([
                    'agent_uid' => $agentUid,
                    'company_name' => $validated['company_name'],
                    'govt_number' => $validated['govt_number'] ?? null,
                    'mobile' => $validated['mobile'],
                    'phone' => $validated['phone'] ?? null,
                    'ceo_name' => $validated['name'] . ' ' . $validated['lastName'],
                    'ceo_contact' => $validated['mobile'] ?? null,
                    'ceo_email' => $validated['company_email'] ?? null,
                    'company_email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'logo' => $logoPath,
                    'e_id' => $eidPaths,
                    'trade_license' => $licencePath,
                    'agent_id' => $user->id,
                ]);

                User::where('id', $user->id)->update(['is_formFilled' => true]);

                // Notify admins
                // $admins = User::where('role', 'admin')->get();
                // foreach ($admins as $admin) {
                //     $admin->notify(new \App\Notifications\NewUserRegistered($user));
                // }
            }

            // ===================================================
            // CUSTOMER REGISTRATION
            // ===================================================
            else if ($role === 'customer') {
                $validated = $request->validate([
                    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
                    'password' => ['required', 'confirmed', Rules\Password::defaults()],
                    'phone' => ['required', 'string', 'max:255'],
                    'role' => ['required', 'in:customer'],
                    'name' => 'required|string|max:255',
                    // 'lastName' => 'nullable|string|max:255',
                    'mobile' => 'required|string|max:15',
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
                ]);

                Customer::create([
                    'user_id' => $user->id,
                    'name' => $validated['name'],
                    // 'last_name' => $validated['lastName'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
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
