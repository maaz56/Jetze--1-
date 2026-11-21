<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentTraveller;
use Auth;
use Illuminate\Http\Request;
use Log;

class AgentTravellerController extends Controller
{
    public function index()
    {
        return response()->json(AgentTraveller::get());
    }

    public function store(Request $request)
    {
        Log::info($request);
      
        $request->validate([
            'type' => 'required|string',
            'gender' => 'required|string',
            'title' => 'required|string|max:10',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'dateOfBirth' => 'required|date',
            'nationality' => 'required|string',
            'docType' => 'required|string',
            'documentNo' => 'required|string',
            'expiryDate' => 'required|date',
            'issueCountry' => 'required|string',
            
        ]);

        //$traveller = AgentTraveller::create($request->all());
        $traveller = AgentTraveller::create([
            'type' => $request->type,
            'gender' => $request->gender,
            'title' => $request->title,
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'date_of_birth' => $request->dateOfBirth,
            'nationality' => $request->nationality,
            'doc_type' => $request->docType,
            'document_no' => $request->documentNo,
            'expiry_date' => $request->expiryDate,
            'issue_country' =>$request->issueCountry,
            'agent_id' => Auth::user()->id,
        ]);
        return response()->json($traveller, 201);
    }

    /**
     * Upsert the travellers entered at checkout so they are available for
     * autofill even if the booking that follows fails. Matched per user on
     * name + document number, so re-saving the same person updates them.
     */
    public function saveFromCheckout(Request $request)
    {
        $data = $request->validate([
            'travellers' => 'required|array|min:1',
            'travellers.*.firstName' => 'required|string',
            'travellers.*.lastName' => 'required|string',
            'travellers.*.type' => 'nullable|string',
            'travellers.*.title' => 'nullable|string|max:10',
            'travellers.*.gender' => 'nullable|string',
            'travellers.*.dob' => 'nullable|string',
            'travellers.*.nationality' => 'nullable|string',
            'travellers.*.documentType' => 'nullable|string',
            'travellers.*.documentNo' => 'nullable|string',
            'travellers.*.expiryDate' => 'nullable|string',
            'travellers.*.issueCountry' => 'nullable|string',
        ]);

        $agentId = (string) Auth::id();
        $saved = [];

        foreach ($data['travellers'] as $t) {
            $firstName = trim($t['firstName']);
            $lastName = trim($t['lastName']);
            $documentNo = trim($t['documentNo'] ?? '');

            $traveller = AgentTraveller::updateOrCreate(
                [
                    'agent_id' => $agentId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'document_no' => $documentNo,
                ],
                [
                    'type' => $t['type'] ?? 'ADT',
                    'gender' => $t['gender'] ?? '',
                    'title' => $t['title'] ?? '',
                    'date_of_birth' => $t['dob'] ?? '',
                    'nationality' => $t['nationality'] ?? '',
                    'doc_type' => $t['documentType'] ?? 'passport',
                    'expiry_date' => $t['expiryDate'] ?? '',
                    'issue_country' => $t['issueCountry'] ?? '',
                ]
            );
            // Bump updated_at so the most recently used traveller lists first.
            $traveller->touch();
            $saved[] = $traveller;
        }

        return response()->json(['saved' => count($saved)], 201);
    }

    public function show(AgentTraveller $agentTraveller)
    {
        return response()->json($agentTraveller);
    }

    // public function update(Request $request, AgentTraveller $agentTraveller)
    // {
    //     $agentTraveller->update($request->all());
    //     return response()->json($agentTraveller);
    // }

    public function destroy(Request $request)
    {
        $agentTraveller = AgentTraveller::findOrFail( $request->id );
        $agentTraveller->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
