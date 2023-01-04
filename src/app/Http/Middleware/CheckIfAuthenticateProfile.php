<?php

namespace Backpack\Profile\app\Http\Middleware;
use Illuminate\Support\Facades\Auth;

use Closure;

class CheckIfAuthenticateProfile
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(!Auth::guard('profile')->check()) {
            return response()->json('Not Authenticated', 403);
        }

        return $next($request);

    }
}
