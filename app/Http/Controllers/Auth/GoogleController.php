<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Log;

class GoogleController extends Controller
{

    protected $appUrl;
    public function __construct()
    {
        $this->appUrl = config('app.url');
    }
    public function redirect()
    {
        Log::info('Google auth redirect initiated.');
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        Log::info('Google auth callback received.');
        $googleUser = Socialite::driver('google')->stateless()->user();
        // Find or Create User
        $user = User::firstOrCreate(
            ['email' => $googleUser->email],
            [
                'name' => $googleUser->name,
                'is_approved' => 1,
                'password' => bcrypt(uniqid()), // random password
                'role' => 'customer',
            ]
        );
        Log::info($user);
        Auth::login($user);

        Customer::firstOrCreate(
            ['user_id' => $user->id],
            [
                'user_id' => $user->id,
                'name' => $googleUser->name,
                'last_name' => '',
                'phone' => '',
                'email' => $googleUser->email,
            ]
        );
        // // If SPA - return token and user details
        // $token = $user->createToken('API Token')->plainTextToken;

        return redirect($this->appUrl );
    }
}
