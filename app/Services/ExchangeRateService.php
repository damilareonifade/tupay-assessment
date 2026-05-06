<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    /** TTL in seconds — short enough to stay fresh, long enough to absorb burst traffic. */
    private const RATE_TTL = 300;

    /**
     * Mocked exchange rates: how many units of $to per 1 unit of $from.
     *
     * @var array<string, array<string, string>>
     */
    private array $mockRates = [
        'NGN' => ['CNY' => '0.004500'],
        'CNY' => ['NGN' => '222.222222'],
    ];

    /**
     * Get the cached exchange rate between two currencies.
     * Returns a BCMath-compatible string (e.g. "0.004500").
     */
    public function getRate(string $from, string $to): string
    {
        $cacheKey = "exchange_rate:{$from}:{$to}";

        return Cache::store('redis')->remember($cacheKey, self::RATE_TTL, function () use ($from, $to) {
            return $this->fetchRate($from, $to);
        });
    }

    /**
     * In a real system this would call an external provider.
     * Here we return a stable mock rate.
     */
    private function fetchRate(string $from, string $to): string
    {
        if (! isset($this->mockRates[$from][$to])) {
            throw new \InvalidArgumentException("Exchange rate for {$from}/{$to} is not available.");
        }

        return $this->mockRates[$from][$to];
    }
}
