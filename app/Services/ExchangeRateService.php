<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    protected $apiKey;
    protected $baseUrl;
    protected $baseCurrency;
    protected $cachePrefix = 'exchange_rates_';
    protected $cacheDuration = 43200; // 12 hours in seconds

    /**
     * Create a new ExchangeRateService instance.
     */
    public function __construct()
    {
        $this->apiKey = config('services.fixer.key');
        $this->baseUrl = config('services.fixer.url', 'http://data.fixer.io/api/');
        $this->baseCurrency = config('services.fixer.base_currency', 'EUR');
    }

    /**
     * Get the exchange rate between two currencies.
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float|null
     */
    public function getRate(string $fromCurrency, string $toCurrency): ?float
    {
        // If currencies are the same, rate is 1:1
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        // Check cache first
        $cacheKey = $this->getCacheKey($fromCurrency, $toCurrency);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Get all rates (more efficient than individual API calls)
            $rates = $this->fetchLatestRates();
            
            if (empty($rates)) {
                return null;
            }

            // If our from currency is the base currency (EUR typically)
            if ($fromCurrency === $this->baseCurrency) {
                $rate = $rates[$toCurrency] ?? null;
            } 
            // If our to currency is the base currency
            elseif ($toCurrency === $this->baseCurrency) {
                $fromRate = $rates[$fromCurrency] ?? null;
                $rate = $fromRate ? (1 / $fromRate) : null;
            }
            // Need to convert via the base currency
            else {
                $fromRate = $rates[$fromCurrency] ?? null;
                $toRate = $rates[$toCurrency] ?? null;

                // Calculate cross rate: to/from
                $rate = ($fromRate && $toRate) ? ($toRate / $fromRate) : null;
            }

            // Cache the result if valid
            if ($rate !== null) {
                Cache::put($cacheKey, $rate, $this->cacheDuration);
            }

            return $rate;
        } catch (\Exception $e) {
            Log::error('Exchange rate API error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate the destination amount based on source amount and real-time exchange rate.
     *
     * @param float $sourceAmount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float|null
     */
    public function convertAmount(float $sourceAmount, string $fromCurrency, string $toCurrency): ?float
    {
        // Make sure currencies are uppercase
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);
        
        // If converting to the same currency, return the amount
        if ($fromCurrency === $toCurrency) {
            return $sourceAmount;
        }
        
        // Get the rate and convert
        $rate = $this->getRate($fromCurrency, $toCurrency);
        if ($rate === null) {
            return null;
        }
        
        return round($sourceAmount * $rate, 2);
    }

    /**
     * Fetch the latest exchange rates from the API.
     *
     * @return array
     */
    public function fetchLatestRates(): array
    {
        $cacheKey = $this->cachePrefix . 'all';
        
        // Return cached rates if available
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            $response = Http::get($this->baseUrl . 'latest', [
                'access_key' => $this->apiKey,
                'base' => $this->baseCurrency,
            ]);
            
            if ($response->successful() && isset($response['rates']) && $response['success'] === true) {
                $rates = $response['rates'];
                Cache::put($cacheKey, $rates, $this->cacheDuration);
                return $rates;
            }
            
            Log::warning('Exchange rate API returned error: ' . json_encode($response->json()));
            return [];
        } catch (\Exception $e) {
            Log::error('Exchange rate API fetch error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all supported currencies.
     *
     * @return array
     */
    public function getSupportedCurrencies(): array
    {
        $cacheKey = $this->cachePrefix . 'currencies';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            $response = Http::get($this->baseUrl . 'symbols', [
                'access_key' => $this->apiKey,
            ]);
            
            if ($response->successful() && isset($response['symbols']) && $response['success'] === true) {
                $currencies = $response['symbols'];
                Cache::put($cacheKey, $currencies, 86400); // Cache for 24 hours
                return $currencies;
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('Error fetching supported currencies: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate the cache key for a currency pair.
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return string
     */
    protected function getCacheKey(string $fromCurrency, string $toCurrency): string
    {
        return $this->cachePrefix . $fromCurrency . '_to_' . $toCurrency;
    }

    /**
     * Clear the exchange rate cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget($this->cachePrefix . 'all');
        Cache::forget($this->cachePrefix . 'currencies');
        // Additional logic to clear specific currency pair caches could be added here
    }
}
