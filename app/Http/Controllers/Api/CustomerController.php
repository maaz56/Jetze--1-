<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\MetaHandler;
use App\Models\Customer;
use App\Models\CustomerSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Log;
use App\Mail\EmailVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class CustomerController extends Controller
{
    public function getCustomerData(Request $request)
    {

        $customer = Customer::with('user')->where('user_id', $request->id)->first();
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer data not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    public function getCustomers(Request $request)
    {

        // Start query with user relation (to access role/email/etc.)
        $query = Customer::with('user')
            ->orderBy('created_at', 'desc');

        // ✅ Apply search filter
        if ($request->filled('search_query')) {
            $searchQuery = $request->search_query;

            $query->where(function ($q) use ($searchQuery) {
                $q->where('name', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('email', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('phone', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhereHas('user', function ($userQuery) use ($searchQuery) {
                        $userQuery->where('email', 'LIKE', '%' . $searchQuery . '%')
                            ->orWhere('role', 'LIKE', '%' . $searchQuery . '%');
                    });
            });
        }

        // ✅ Apply pagination if requested
        if ($request->has('page')) {
            $customers = $query->paginate(Constants::$LARGER_LIMIT);
            $meta = MetaHandler::generate($customers);
            $data = $customers->items();
        } else {
            $customers = $query->get();
            $meta = null;
            $data = $customers;
        }

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'Customers retrieved successfully.',
            ],
            'data' => $data,
            'meta' => $meta,
        ], 200);
    }

    public function getCustomerSettings()
    {
        $settings = CustomerSetting::first();
        if (!$settings) {
            return response()->json([
                'success' => false,
                'message' => 'Customer settings not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function updateCustomerSettings(Request $request)
    {
        $settings = CustomerSetting::first();
        if (!$settings) {
            return response()->json([
                'success' => false,
                'message' => 'Customer settings not found.',
            ], 404);
        }

        if ($request->has('is_card_allowed')) {
            $settings->is_card_allowed = $request->boolean('is_card_allowed');
        }
        if ($request->has('is_booking_allowed')) {
            $settings->is_booking_allowed = $request->boolean('is_booking_allowed');
        }
        if ($request->has('one_bill_charges')) {
            $settings->one_bill_charges = $request->one_bill_charges;
        }
        if ($request->has('one_bill_fixed_charge')) {
            $settings->one_bill_fixed_charge = $request->one_bill_fixed_charge;
        }
        if ($request->has('one_bill_percentage_charge')) {
            $settings->one_bill_percentage_charge = $request->one_bill_percentage_charge;
        }
        if ($request->has('void_charges')) {
            $settings->void_charges = $request->void_charges;
        }
        $settings->save();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }
    public function updateCustomerData(Request $request)
{
    Log::info('Update Customer Data Request:', $request->all());

    $customer = Customer::find($request['id']);
    if (!$customer) {
        return response()->json([
            'success' => false,
            'message' => 'Customer not found.',
        ], 404);
    }

    // Update customer data
    $customer->name = $request['name'];
    $customer->last_name = $request['last_name'];
    $customer->email = $request['email'];
    $customer->phone = $request['phone'];
    $customer->address = $request['address'];
    $customer->company_name = $request['companyName'];
    $customer->preferred_currency = $request['preferredCurrency'];
    $customer->save();

    // 🔥 If request has email, update user email as well
    if ($request->filled('email') && $customer->user_id) {
        $user = User::find($customer->user_id);

        if ($user) {
            $user->email = $request['email'];
            $user->save();
        }
    }

    return response()->json([
        'success' => true,
        'data' => $customer,
    ]);
}


    public function updateCustomerType(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'mode' => 'required',
        ]);


        $customer = Customer::with('user')->where('user_id', $request->user_id)->first();

        if (!$customer || !$customer->user) {
            return response()->json([
                'success' => false,
                'message' => 'Customer or user not found.',
            ], 404);
        }

        $customer->user->mode = $request->mode;
        $customer->user->save();

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    public function resendVerificationEmail(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($request->user_id);

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.',
            ], 400);
        }

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

        return response()->json([
            'success' => true,
            'message' => 'Verification link sent successfully.',
        ]);
    }


}
