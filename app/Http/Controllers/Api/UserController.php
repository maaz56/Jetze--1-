<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\MetaHandler;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Log;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function index(Request $request)
    // {

    //     Log::info($request);
    //     $query = User::query()->with('agentData')->orderBy('created_at');

    //     if ($request->search_query) {
    //         $searchQuery = $request->search_query;
    //         $query->where(
    //             "name",
    //             "LIKE",
    //             "%" . $searchQuery . "%"
    //         )->orWhere(
    //                 "email",
    //                 "LIKE",
    //                 "%" . $searchQuery . "%"
    //             )->orWhere(
    //                 "role",
    //                 "LIKE",
    //                 "%" . $searchQuery . "%"
    //             );
    //     }

    //     // Apply approval_status filter
    //     if ($request->has('approval_status')) {
    //         $query->where('approval_status', $request->approval_status);
    //     }

    //     // Apply role filter
    //     if ($request->has('role')) {
    //         $query->where('role', $request->role);
    //     }

    //     $users = $query->paginate(Constants::$PAGE_LIMIT);

    //     return response()->json([
    //         'success' => true,
    //         'message' => [
    //             'status' => 'success',
    //             'description' => 'Users retrieved Successfully.',
    //         ],
    //         'data' => $users->items(),
    //         'meta' => MetaHandler::generate($users)
    //     ], 200);
    // }

public function index(Request $request)
{
    // Log all request data
    Log::info($request->all());

    $user = Auth::user();

    $query = User::query()->with('agentData')->orderBy('created_at', 'desc');

    // Admin can see all users, salesman can see only their own
    if ($user && $user->role === 'salesman') {
        $query->whereHas('agentData', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    // Apply search filter (only if not null/empty)
    if ($request->filled('search_query')) {
        $searchQuery = $request->search_query;
        $query->where(function ($q) use ($searchQuery) {
            $q->where("name", "LIKE", "%" . $searchQuery . "%")
              ->orWhere("email", "LIKE", "%" . $searchQuery . "%")
              ->orWhere("role", "LIKE", "%" . $searchQuery . "%")
              ->orWhereHas('agentData', function ($agentQuery) use ($searchQuery) {
                  $agentQuery->where("company_name", "LIKE", "%" . $searchQuery . "%")
                             ->orWhere("ceo_contact", "LIKE", "%" . $searchQuery . "%")
                             ->orWhere("agent_uid", "LIKE", "%" . $searchQuery . "%");
              });
        });
    }

    // Apply approval_status filter (only if not null)
    if ($request->filled('approval_status') && $request->approval_status !== 'all') {
        $query->where('is_approved', $request->approval_status);
    }

    // Apply role filter (only if not null)
    if ($request->filled('role')) {
        $query->where('role', $request->role);
    }

    // ✅ Check if page parameter is present
    if ($request->has('page')) {
        $users = $query->paginate(Constants::$PAGE_LIMIT);
        $meta = MetaHandler::generate($users);
        $data = $users->items();
    } else {
        $users = $query->get();
        $meta = null; // No pagination meta
        $data = $users;
    }

    $salesMenAgentCount = $this->staffMeta();

    return response()->json([
        'success' => true,
        'message' => [
            'status' => 'success',
            'description' => 'Users retrieved successfully.',
        ],
        'salesMenAgentCount' => $salesMenAgentCount,
        'data' => $data,
        'meta' => $meta,
    ], 200);
}




    public function staffMeta()
    {
        // Fetch users with either 'salesman' or 'reservation' role
        $salesMen = User::whereIn('role', ['salesman', 'reservation'])->latest()->get();
        foreach ($salesMen as $salesman) {  
            $salesman->agent_count = $salesman->staffMeta()->count();
        }
        Log::info($salesMen);
        return $salesMen;
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // 
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = $request->validate([
            'role' => 'required|integer',
            'name' => 'required',
            'phone' => 'required|integer',
            'email' => 'required|email',
            'password' => 'required',
        ]);
      
        $user = new User();
        $user->role = $request->role;
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'User created successfully.'
            ]
        ], 201);
    }

    public function storAgentData(Request $request)
    {
        Log::info($request);
        return $request;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::where('id', $id)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'User not found.'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // 
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {

        $user = User::where('id', $request->id)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'User not found.'
                ]
            ], 404);
        }

        if ($request->is_approved) {
            $user->is_approved = $request->is_approved;
            if ($user->is_approved) {
                $user->notify(new \App\Notifications\UserApproved($user));
            }
        }
        if ($request->price_margin) {
            $user->price_margin = $request->price_margin;
        }
        $user->save();

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'User has been updated successfully.'
            ]
        ], 200);
    }

    public function updateStatus(Request $request)
    {

        // Validate incoming data
        $request->validate([
            'userId' => 'required|exists:users,id',
            'status' => 'required|boolean',
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        // Update the status
        $user->is_approved = $request->status;
        $user->save();

        // Notify user if approved
        if ($user->is_approved) {
            $user->notify(new \App\Notifications\UserApproved($user));
        }

        return response()->json([
            'message' => 'User approval status updated successfully.',
            'user' => $user,
        ]);
    }

    public function updateCardAllowance(Request $request)
    {
        // Validate incoming data
        $request->validate([
            'userId' => 'required|exists:users,id',
            'is_card_allowed' => 'required',
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Update the card allowance
        $user->is_card_allowed = $request->is_card_allowed;
        $user->save();

        return response()->json([
            'message' => 'User card allowance updated successfully.',
            'user' => $user,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::where('id', $id)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'User not found.'
                ]
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'User has been deleted successfully.'
            ]
        ], 200);
    }


    public function saveStaff(Request $request)
    {
        Log::info($request);
        try {
            $validator = $request->validate([
                'role' => 'required|string',
                'name' => 'required',
                'email' => 'required|email|unique:users,email',
                'password' => 'required',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'Validation failed.',
                    'errors' => $e->errors()
                ]
            ], status: 422);
        }

        $user = new User();
        $user->role = $request->role;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->is_approved = 1;
        $user->email_verified_at = now();
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'Staff created successfully.'
            ]
        ], 201);
    }

    public function updateStaff(Request $request)
    {
        Log::info($request);

        $validator = $request->validate([
            'id' => 'required|exists:users,id',
            'role' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::find($request->id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'User not found.'
                ]
            ], 404);
        }

        $user->role = $request->role;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->is_approved = 1;
        $user->email_verified_at = now();
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => [
                'status' => 'success',
                'description' => 'Staff updated successfully.'
            ]
        ], 200);
    }
    public function UsersSummary(Request $request)
    {
        if (Auth::user()->role != 'admin') {
            return response()->json([
                'success' => false,
                'message' => [
                    'status' => 'error',
                    'description' => 'Unauthorized'
                ]
            ], 403);
        } else {
            $totalAgents = User::where('role', 'agent')->count();
            $approvedAgents = User::where('is_approved', 1)->where('role', 'agent')->count();
            $pendingAgents = User::where('is_approved', 0)->where('role', 'agent')->count();
            $total_users = User::where('role', 'user')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_agents' => $totalAgents,
                    'approved_agents' => $approvedAgents,
                    'pending_agents' => $pendingAgents,
                    'total_users' => $total_users,
                ]
            ], 200);
        }

    }



}
