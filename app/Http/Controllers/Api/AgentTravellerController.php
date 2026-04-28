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
