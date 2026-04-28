<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\MetaHandler;
use App\Models\Customer;
use App\Models\CustomerSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Log;

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

        $settings->is_card_allowed = $request->is_card_allowed;
        $settings->is_booking_allowed = $request->is_booking_allowed;
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


}
