<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\CurrencyRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CurrencyController extends Controller
{
    private const BASE_CURRENCY = 'AED';

    public function __construct(private readonly CurrencyRateService $currencyRateService)
    {
    }

    public function index(Request $request)
    {
        $currencies = Currency::query()
            ->when(
                $request->user()?->role !== 'admin',
                fn ($query) => $query->where('is_enabled', true),
            )
            ->when($request->filled('searchQuery'), function ($query) use ($request) {
                $searchQuery = strtoupper(trim($request->searchQuery));
                $query->where('code', 'LIKE', '%' . $searchQuery . '%');
            })
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $currencies], 200);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

        $validated = $request->validate([
            'code' => 'required|string|size:3|alpha|unique:currencies,code',
            'name' => 'nullable|string',
            'symbol' => 'nullable|string',
            'exchange_rate' => 'nullable|numeric|gt:0',
            'decimal_places' => 'nullable|integer|between:0,6',
            'is_enabled' => 'nullable|boolean',
            'rate_change_reason' => 'nullable|string|max:1000',
        ]);

        $rateChangeReason = trim((string) ($validated['rate_change_reason'] ?? ''));
        unset($validated['rate_change_reason']);
        $validated['code'] = strtoupper($validated['code']);
        $validated['decimal_places'] = $validated['decimal_places'] ?? 2;
        $validated['is_enabled'] = $validated['is_enabled'] ?? true;
        $validated['is_base'] = $validated['code'] === self::BASE_CURRENCY;

        if ($validated['is_base']) {
            $validated['exchange_rate'] = 1;
            $validated['is_enabled'] = true;
        } elseif (empty($validated['exchange_rate'])) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'A rate is required for a non-base currency.',
            ]);
        } elseif ($rateChangeReason === '') {
            throw ValidationException::withMessages([
                'rate_change_reason' => 'A reason is required when setting a currency rate.',
            ]);
        }

        $currency = DB::transaction(function () use ($validated, $rateChangeReason, $request) {
            $currency = Currency::create($validated);
            $this->currencyRateService->recordInitialRate(
                $currency,
                $request->user(),
                $rateChangeReason !== '' ? $rateChangeReason : 'Base currency setup',
            );

            return $currency;
        });

        return response()->json(['message' => 'Currency created successfully', 'data' => $currency], 201);
    }

    public function update(Request $request)
    {
        $this->ensureAdmin($request);
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

        $validated = $request->validate([
            'code' => 'required|string|exists:currencies,code',
            'name' => 'nullable|string',
            'symbol' => 'nullable|string',
            'exchange_rate' => 'nullable|numeric|gt:0',
            'decimal_places' => 'nullable|integer|between:0,6',
            'is_enabled' => 'nullable|boolean',
            'rate_change_reason' => 'nullable|string|max:1000',
        ]);

        $currency = Currency::where('code', $validated['code'])->first();
        if (!$currency) {
            return response()->json(['message' => 'Currency not found'], 404);
        }

        $rateChangeReason = trim((string) ($validated['rate_change_reason'] ?? ''));
        unset($validated['code'], $validated['rate_change_reason']);

        if (($validated['exchange_rate'] ?? null) === null) {
            unset($validated['exchange_rate']);
        }

        $rateChanged = array_key_exists('exchange_rate', $validated)
            && !$currency->is_base
            && $this->currencyRateService->hasChanged($currency, $validated['exchange_rate']);

        if ($rateChanged && $rateChangeReason === '') {
            throw ValidationException::withMessages([
                'rate_change_reason' => 'A reason is required when changing a currency rate.',
            ]);
        }

        if ($currency->is_base) {
            $validated['exchange_rate'] = 1;
            $validated['is_enabled'] = true;
            $validated['is_base'] = true;
        }

        $currency = DB::transaction(function () use ($currency, $validated, $rateChanged, $rateChangeReason, $request) {
            if ($rateChanged) {
                $this->currencyRateService->changeRate(
                    $currency,
                    $validated['exchange_rate'],
                    $request->user(),
                    $rateChangeReason,
                );
                unset($validated['exchange_rate']);
            }

            if ($validated !== []) {
                $currency->update($validated);
            }

            return $currency->fresh();
        });

        return response()->json(['message' => 'Currency updated successfully', 'data' => $currency], 200);
    }

    public function rateHistory(Request $request, string $code)
    {
        $this->ensureAdmin($request);

        $currency = Currency::where('code', strtoupper($code))->firstOrFail();

        return response()->json([
            'data' => $currency->rateHistories()
                ->with('changedBy:id,name,email')
                ->latest()
                ->get(),
        ]);
    }

    public function destroy(Request $request)
    {
        return response()->json([
            'message' => 'Currencies cannot be deleted. Disable the currency instead.',
        ], 405);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->role === 'admin',
            403,
            'Only the administrator can manage currencies.',
        );
    }
}
