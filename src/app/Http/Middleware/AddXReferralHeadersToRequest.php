<?php

namespace Backpack\Profile\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddXReferralHeadersToRequest
{
    public function handle(Request $request, Closure $next)
    {
        $referralCode = $request->header('X-Referral-Code');

        $request->merge([
            'referral_code' => $referralCode,
        ]);

        return $next($request);
    }
}
