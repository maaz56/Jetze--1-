<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AgentCreatedMail;
use App\Mail\ChargesMail;
use App\Mail\ChargesRequestMail;
use App\Models\AgentCharge;
use App\Models\AgentData;
use App\Models\User;
use App\Services\ZohoService;
use Auth;
use DB;
use Hash;
use Illuminate\Http\Request;
use Log;
use Mail;
use Storage;
use Validator;

class AgentDataController extends Controller
{

    protected $zohoService;
    public function __construct()
    {
        $this->zohoService = new ZohoService();
    }

    public function store(Request $request)
    {


        Log::info("Agnet");
        Log::info($request);

        $validatedData = $request->validate([
            'company_name' => 'required|string|max:255',
            'govt_number' => 'nullable|string|max:255',
            'mobile' => 'required|string|max:15',
            'phone' => 'nullable|string|max:15',
            'ceo_name' => 'nullable|string|max:255',
            'ceo_contact' => 'nullable|string|max:15',
            'ceo_email' => 'nullable|email|max:255',
            'company_email' => 'nullable|email|max:255',
            'agent_id' => 'nullable|exists:users,id',
            'address' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
            'e_id' => 'nullable|image|max:2048',
            'license' => 'nullable|image|max:2048'// 2MB max size
        ]);

        // Start Transaction
        DB::beginTransaction();

        try {
            $logoPath = null;
            $eidPath = null;
            $licencePath = null;
            if ($request->hasFile('logo', 'eid', 'licence')) {
                $logoPath = $request->file('logo')->store('public');
                $eidPath = $request->file('e_id')->store('public');
                $licencePath = $request->file('license')->store('public');
            }
            $logoUrl = $logoPath ? Storage::url($logoPath) : null;
            $eidUrl = $eidPath ? Storage::url($eidPath) : null;
            $licenceUrl = $licencePath ? Storage::url($licencePath) : null;

            // Create AgentData record
            $agentData = AgentData::create([
                'company_name' => $validatedData['company_name'],
                'govt_number' => $validatedData['govt_number'] ?? null,
                'mobile' => $validatedData['mobile'],
                'phone' => $validatedData['phone'] ?? null,
                'ceo_name' => $validatedData['ceo_name'] ?? null,
                'ceo_contact' => $validatedData['ceo_contact'] ?? null,
                'ceo_email' => $validatedData['ceo_email'] ?? null,
                'company_email' => $validatedData['company_email'] ?? null,
                'agent_id' => $validatedData['agent_id'] ?? null,
                'address' => $validatedData['address'] ?? null,
                'logo' => $logoUrl,
                'e_id' => $eidUrl,
                'trade_license' => $licenceUrl
            ]);


            // Update is_formFilled for the related user
            if ($request->has('agent_id')) {
                User::where('id', $request->agent_id)->update(['is_formFilled' => true]);
            }
            // Commit Transaction (everything is successful)
            DB::commit();

            return response()->json([
                'message' => 'Agent data created successfully',
                'data' => $agentData
            ], 201);
        } catch (\Exception $e) {
            // Rollback Transaction (undo all changes if there's an error)
            DB::rollBack();

            return response()->json([
                'message' => 'Error creating agent data',
                'error' => $e->getMessage()
            ], 500);
        }

        // $logoPath = null;
        // if ($request->hasFile('logo')) {
        //     $logoPath = $request->file('logo')->store('public');
        // }
        // $logoUrl = Storage::url($logoPath);

        // // $agentData = AgentData::create($validatedData);


        // // Create AgentData record
        // $agentData = AgentData::create([
        //     'company_name' => $request->input('company_name'),
        //     'govt_number' => $request->input('govt_number'),
        //     'mobile' => $request->input('mobile'),
        //     'phone' => $request->input('phone'),
        //     'ceo_name' => $request->input('ceo_name'),
        //     'ceo_contact' => $request->input('ceo_contact'),
        //     'ceo_email' => $request->input('ceo_email'),
        //     'company_email' => $request->input('company_email'),
        //     'agent_id' => $request->input('agent_id'),
        //     'address' => $request->input('address'),
        //     'logo' => $logoUrl,
        // ]);

        // return response()->json([
        //     'message' => 'Agent data created successfully',
        //     'data' => $agentData
        // ], 201);

    }

    public function setAgentMargin(Request $request)
    {
        Log::info($request->agentId);
        // Validate the request data
        $validatedData = $request->validate([
            'margin_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);
        $agentData = null;
        if ($request->has('margin_amount')) {

            $agentData = AgentData::where('agent_id', $request->agentId)->first();

            // Update the agent's margin amount
            $agentData->update([
                'margin_amount' => $validatedData['margin_amount'] ?? null,
            ]);
        } else if ($request->has('discount_amount')) {

            $agentData = AgentData::where('agent_id', $request->agentId)->first();

            // Update the agent's margin amount
            $agentData->update([
                'agent_discount' => $validatedData['discount_amount'] ?? null,
            ]);
        }


        // Return a success response
        return response()->json([
            'message' => 'Margin amount updated successfully.',
            'data' => $agentData,
        ], 200);
    }

    public function update(Request $request)
    {
        Log::info($request);
        // Validate incoming data
        $validatedData = $request->validate([
            'company_name' => 'required|string|max:255',
            'govt_number' => 'nullable|string|max:255',
            'mobile' => 'required|string|max:15',
            'phone' => 'nullable|string|max:15',
            'ceo_name' => 'nullable|string|max:255',
            'ceo_contact' => 'nullable|string|max:15',
            'ceo_email' => 'nullable|email|max:255',
            'company_email' => 'nullable|email|max:255',
            'agent_id' => 'nullable|exists:users,id',
            'address' => 'nullable|string',
            'logo' => 'nullable|max:2048', // 2MB max size
        ]);
        $agentData = AgentData::where('agent_id', $request->agent_id)->first();

        // Retain old paths
        $logoUrl = $agentData->logo;
        $licenseUrl = $agentData->trade_license;
        $eidUrl = $agentData->e_id;

        // Handle Logo Upload
        if ($request->hasFile('logo')) {
            if (!empty($agentData->logo)) {
                $oldLogoPath = str_replace('/storage', 'public', $agentData->logo);
                if (Storage::exists($oldLogoPath)) {
                    Storage::delete($oldLogoPath);
                }
            }
            $logoPath = $request->file('logo')->store('public');
            $logoUrl = Storage::url($logoPath);
        }

        // Handle Emirates ID Upload
        if ($request->hasFile('e_id')) {
            if (!empty($agentData->e_id)) {
                $oldEidPath = str_replace('/storage', 'public', $agentData->e_id);
                if (Storage::exists($oldEidPath)) {
                    Storage::delete($oldEidPath);
                }
            }
            $eidPath = $request->file('e_id')->store('public');
            $eidUrl = Storage::url($eidPath);
        }

        // Handle Trade License Upload
        if ($request->hasFile('license')) {
            if (!empty($agentData->trade_license)) {
                $oldLicensePath = str_replace('/storage', 'public', $agentData->trade_license);
                if (Storage::exists($oldLicensePath)) {
                    Storage::delete($oldLicensePath);
                }
            }
            $licensePath = $request->file('license')->store('public');
            $licenseUrl = Storage::url($licensePath);
        }

        // Update AgentData record
        $agentData->update([
            'company_name' => $request->input('company_name'),
            'govt_number' => $request->input('govt_number'),
            'mobile' => $request->input('mobile'),
            'phone' => $request->input('phone'),
            'ceo_name' => $request->input('ceo_name'),
            'ceo_contact' => $request->input('phone'),
            'ceo_email' => $request->input('ceo_email'),
            'company_email' => $request->input('company_email'),
            'agent_id' => $request->input('agent_id'),
            'address' => $request->input('address'),
            'logo' => $logoUrl,
            'e_id' => $eidUrl,
            'trade_license' => $licenseUrl,
        ]);


        if ($request->has('password') && !empty($request->input('password'))) {
            $user = User::where('id', $request->agent_id);
            $user->update([

                'password' => $request->input('password') ? Hash::make($request->input('password')) : $user->password,
            ]);
        }
        return response()->json([
            'message' => 'Agent data updated successfully',
            'data' => $agentData
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {

        $user = User::with(['agentData'])->find($request->userId);

        // $agentData = AgentData::where('agent_id', $request->userId)->first();
        // Log::info($agentData);

        return response()->json($user);
    }

    public function saveAdminAgent(Request $request)
    {
        $request->validate([
            // 'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required'],
        ]);
        $plainPassword = $request->password;

        $user = new User();
        $user->email = $request->company_email;
        $user->password = $request->password;
        $user->role = $request->role;
        $user->is_approved = 1;
        $user->is_formFilled = 1;
        $user->email_verified_at = now();
        $user->save();

        $validatedData = $request->validate([
            'company_name' => 'required_if:role,agent|string|max:255|nullable',
            'govtNo' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:15',
            'phone' => 'nullable|string|max:15',
            'ceo_name' => 'nullable|string|max:255',
            'ceo_contact' => 'nullable|string|max:15',
            'ceo_email' => 'nullable|email|max:255',
            'company_email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'logo' => 'nullable|max:2048', // 2MB max size
            'currency' => 'nullable|string|max:10',
            'language' => 'nullable|string|max:10',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('public');
        }
        $logoUrl = $logoPath ? Storage::url($logoPath) : null;
        $logoPath = $request->hasFile('logo') ? $request->file('logo')->store('public') : null;
        $eidPath = $request->hasFile('e_id') ? $request->file('e_id')->store('public') : null;
        $licencePath = $request->hasFile('license') ? $request->file('license')->store('public') : null;


        // Get the last inserted id or max number
        $lastId = AgentData::max('id') ?? 0;
        $nextNumber = str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
        $year = date('y'); // Get last 2 digits of current year
        $agentUid = "AGN-" . $year . $nextNumber;
        $userAuth = Auth::user();
        $agentData = AgentData::create([
            'agent_uid' => $agentUid,
            'user_id' => $userAuth->id,
            'company_name' => $validatedData['company_name'],
            'govt_number' => $validatedData['govtNo'] ?? null,
            'mobile' => $validatedData['phone'],
            'phone' => $validatedData['phone'] ?? null,
            'ceo_name' => $validatedData['ceo_name'] ?? null,
            'ceo_contact' => $validatedData['phone'] ?? null,
            'ceo_email' => $validatedData['ceo_email'] ?? null,
            'company_email' => $validatedData['company_email'] ?? null,
            'agent_id' => $user->id,
            'address' => $validatedData['address'] ?? null,
            'currency' => $validatedData['currency'] ?? null,
            'language' => $validatedData['language'] ?? null,
            'logo' => $logoUrl,
            'e_id' => $eidPath ? Storage::url($eidPath) : null,
            'trade_license' => $licencePath ? Storage::url($licencePath) : null,
        ]);
        Log::info($agentData);
        // Send email
        // Mail::to($user->email)->send(new AgentCreatedMail($user, $agentData, $plainPassword));
        return response()->json([
            'message' => 'Agent created successfully',
            'data' => $agentData
        ], 201);
    }

    public function setAgentCharges(Request $request)
    {
        Log::info($request->all());

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'chargeType' => 'required|string',
            'additional_details' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:pdf|max:2048', // PDF max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $receiptPath = null;

        // Handle file upload if provided
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
            // stored in storage/app/public/receipts
        }
        $user = Auth::user();

        $isApproved = $user->role === 'admin' ? 1 : 0;

        $charge = AgentCharge::create([
            'date' => $request->input('date'),
            'user_id' => $request->input('user_id'),
            'charged_by' => $user->id,
            'amount' => $request->input('amount'),
            'payment_type' => $request->input('chargeType'), // match frontend key
            'additional_details' => $request->input('additional_details'),
            'receipt' => $receiptPath,
            'is_approved' => $isApproved,
        ]);

        // Enable email notifications for charge/refund requests
        $charge = AgentCharge::with(['agent.agentData', 'chargedBy'])->find($charge->id);
        if ($user->role === 'admin') {
            if (!empty($charge->agent->email)) {
                Mail::to($charge->agent->email)->queue(
                    (new ChargesMail($charge))->afterCommit()
                );
            }
            // Zoho integration remains disabled unless needed
        } else {
            $admin = User::where('role', 'admin')->first();
            $recipients = array_values(array_unique(array_filter([
                $charge->agent->email,
                $admin->email ?? null,
            ])));
            foreach ($recipients as $email) {
                Mail::to($email)->queue(
                    (new ChargesRequestMail($charge))->afterCommit()
                );
            }
        }


        return response()->json([
            'message' => 'Charges added successfully.',
            'data' => $charge,
        ], 201);
    }

    public function updateAgentChargeStatus(Request $request)
    {
        Log::info($request->all());

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:agent_charges,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $charge = AgentCharge::find($request->input('id'));
        $charge->is_approved = $request->input('is_approved');
        $charge->save();
        $charge = AgentCharge::with(['agent.agentData', 'chargedBy'])->find($charge->id);
        if (!empty($charge->agent->email)) {
            Mail::to($charge->agent->email)->queue(
                (new ChargesMail($charge))->afterCommit()
            );
        }
        $params = [
            'customer_name' => $charge->agent->agentData->ceo_name == null ? 'Agent' : $charge->agent->agentData->ceo_name,
            'customer_email' => $charge->agent->email ?? 'N/A',
            'customer_phone' => $charge->agent->agentData->mobile ?? 'N/A',
            'company_name' => $charge->agent->agentData->company_name ?? 'N/A',
            'customer_country' => "Dubai",
            'item_name' => $charge->payment_type,
            'item_description' => $charge->additional_details,
            'amount' => $charge->amount,
            'currency_code' => 'PKR',
            'currency_symbol' => ' د.إ',
        ];

        $invoice = $this->zohoService->createInvoice($params);
        $payment = $this->zohoService->createPayment($invoice);
        Log::info($invoice);
        return response()->json([
            'message' => 'Agent charge status updated successfully.',
            'data' => $charge,
        ], 200);
    }

    public function showCharges(Request $request)
    {
        $charge = AgentCharge::find($request->user_id);
        if (!$charge) {
            return response()->json(['message' => 'Agent Charge not found'], 404);
        }
        return response()->json($charge);
    }

    public function downloadReceipt($filename)
    {
        $path = storage_path("app/public/receipts/{$filename}");

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }
    public function showAllCharges(Request $request)
    {

        Log::info($request);
        if ($request->userRole == 'agent') {
            $charge = AgentCharge::with(['agent.agentData', 'chargedBy'])->where('user_id', $request->userId)->where('is_approved', 1)->latest()->get();
            if (!$charge) {
                return response()->json(['message' => 'Agent Charge not found'], 404);
            }
            return response()->json($charge);
        } else if ($request->userRole == 'admin') {
            $charge = AgentCharge::with(['agent.agentData', 'chargedBy'])->latest()->get();
            if (!$charge) {
                return response()->json(['message' => 'Agent Charge not found'], 404);
            }
            return response()->json($charge);
        }
    }

    public function destroy($id)
    {
        $charge = AgentCharge::find($id);
        if (!$charge) {
            return response()->json(['message' => 'Agent Charge not found'], 404);
        }
        $charge->delete();
        return response()->json(['message' => 'Agent Charge deleted successfully']);
    }
}
