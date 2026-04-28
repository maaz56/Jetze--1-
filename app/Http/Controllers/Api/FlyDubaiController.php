<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FlyDubaiApiService;
use Illuminate\Http\Request;
use Log;

class FlyDubaiController extends Controller
{

    protected $flydubaiApiService;

    public function __construct(FlyDubaiApiService $flydubaiApiService)
    {
        $this->flydubaiApiService = $flydubaiApiService;
    }
    public function AddToCart(Request $request)
    {
        Log::info($request->all());
        $fareReference = $request['fare_reference'];

        $cartResponse = $this->flydubaiApiService->AddToCart($request,  $fareReference);
        Log::info("Add to Cart Response: " . json_encode($cartResponse));
    }
}
