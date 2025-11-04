<?php

namespace Backpack\Profile\app\Http\Controllers\Api;

use Backpack\Profile\app\Services\CurrencyConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

class PointsRateController extends Controller
{
    public function __construct(protected CurrencyConverter $converter)
    {
    }

    public function __invoke(): JsonResponse
    {
        if (!\Settings::get('profile.points.enabled', false)) {
            return response()->json([
                'message' => 'Points wallet is disabled.',
            ], 404);
        }

        $pointsCode = (string)\Settings::get('profile.points.key', 'POINT');
        $pointsName = (string)\Settings::get('profile.points.name', $pointsCode);

        $configuredCurrencies = (array)\Settings::get('profile.currencies', config('profile.currencies', []));

        $rates = [];

        try {
            foreach ($configuredCurrencies as $currency) {
                $code = strtoupper((string)($currency['code'] ?? ''));
                if ($code === '') {
                    continue;
                }

                $rate = $this->converter->convert(1.0, $pointsCode, $code, 10);

                $rates[] = [
                    'code' => $code,
                    'name' => (string)($currency['name'] ?? $code),
                    'rate' => $rate,
                ];
            }
        } catch (Throwable $exception) {
            Log::error('Failed to resolve points exchange rates', [
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'Unable to fetch points exchange rates.',
            ], 503);
        }

        // Add the points currency itself for completeness.
        array_unshift($rates, [
            'code' => $pointsCode,
            'name' => $pointsName,
            'rate' => 1.0,
        ]);

        return response()->json([
            'base' => [
                'code' => $pointsCode,
                'name' => $pointsName,
            ],
            'rates' => $rates,
        ]);
    }
}
