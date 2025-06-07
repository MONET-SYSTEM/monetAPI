<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }


    //Display a listing of the resource.
    public function index(Request $request): Response
    {
        $request->validate([
            'per_page' => 'nullable|numeric|min:1',
            'page' => 'nullable|numeric|min:1',
            'search' => 'nullable|string|max:255',
        ]);

        // get currencies
        $currencies = $this->currencyService->getAll($request, $request->per_page);
        $results = [
            'currencies' => CurrencyResource::collection($currencies)
        ];

        // handle pagination
        if ($request->per_page) {
            $results['per_page'] = $currencies->perPage();
            $results['current_page'] =  $currencies->currentPage();
            $results['last_page'] =  $currencies->lastPage();
            $results['total'] = $currencies->total();
        }

        // return
        return response([
            'message' => __('app.data_load_success', [
                'data' => __('app.currencies')
            ]),
            'results' => $results
        ]);
    }


    //Get currency by uuid
    public function get(Request $request, string $uuid): Response
    {
        // get currency
        $currency = $this->currencyService->getByUuid($uuid);

        // return
        return response([
            'message' => __('app.data_load_success', [
                'data' => __('app.currency')
            ]),
            'results' => [
                'currency' => new CurrencyResource($currency)
            ]
        ]);
    }

    /**
     * Store a newly created currency
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:currencies,code',
            'symbol' => 'required|string|max:10',
            'symbol_position' => 'required|in:before,after',
            'thousand_separator' => 'required|string|max:1',
            'decimal_separator' => 'required|string|max:1',
            'decimal_places' => 'required|integer|min:0|max:4',
            'active' => 'nullable|boolean',
        ]);

        $currency = $this->currencyService->create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'symbol' => $request->symbol,
            'symbol_position' => $request->symbol_position,
            'thousand_separator' => $request->thousand_separator,
            'decimal_separator' => $request->decimal_separator,
            'decimal_places' => $request->decimal_places,
            'active' => $request->active ?? true,
        ]);

        return response()->json([
            'message' => __('app.data_create_success', [
                'data' => __('app.currency')
            ]),
            'results' => [
                'currency' => new CurrencyResource($currency)
            ]
        ], 201);
    }

    /**
     * Update an existing currency
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:currencies,code,' . $uuid . ',uuid',
            'symbol' => 'required|string|max:10',
            'symbol_position' => 'required|in:before,after',
            'thousand_separator' => 'required|string|max:1',
            'decimal_separator' => 'required|string|max:1',
            'decimal_places' => 'required|integer|min:0|max:4',
            'active' => 'nullable|boolean',
        ]);

        $currency = $this->currencyService->getByUuid($uuid);
        
        $updatedCurrency = $this->currencyService->update($currency, [
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'symbol' => $request->symbol,
            'symbol_position' => $request->symbol_position,
            'thousand_separator' => $request->thousand_separator,
            'decimal_separator' => $request->decimal_separator,
            'decimal_places' => $request->decimal_places,
            'active' => $request->active ?? $currency->active,
        ]);

        return response()->json([
            'message' => __('app.data_update_success', [
                'data' => __('app.currency')
            ]),
            'results' => [
                'currency' => new CurrencyResource($updatedCurrency)
            ]
        ]);
    }

    /**
     * Delete a currency (mark as inactive)
     */
    public function destroy(string $uuid): JsonResponse
    {
        $currency = $this->currencyService->getByUuid($uuid);
        
        // Check if currency is being used by any accounts
        if ($currency->accounts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete currency as it is being used by one or more accounts.',
                'error' => 'currency_in_use'
            ], 422);
        }

        $this->currencyService->delete($currency);

        return response()->json([
            'message' => __('app.data_delete_success', [
                'data' => __('app.currency')
            ])
        ]);
    }
}