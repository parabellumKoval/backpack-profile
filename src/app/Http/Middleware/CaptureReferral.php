<?php

namespace Backpack\Profile\app\Http\Middleware;

use Closure;

class CaptureReferral
{
    public function handle($request, Closure $next)
    {
        $param = \Settings::get('profile.referrals.url_param', 'ref');
        $cookie = \Settings::get('profile.referrals.cookie.name', 'ref_code');
        $ttlDays = (int) \Settings::get('profile.referrals.link_ttl_days', 30);
        $logClicks = (bool) \Settings::get('profile.referrals.log_clicks', true);

        if ($code = $request->query($param)) {
            // opc: лог клика
            if ($logClicks) {
                \DB::table('ak_referral_clicks')->insert([
                    'referral_code' => $code,
                    'request_ip'    => $request->ip(),
                    'user_agent'    => substr((string) $request->userAgent(), 0, 255),
                    'landing_path'  => substr($request->path(), 0, 255),
                    'created_at'    => now(),
                ]);
            }

            // кука c iat (issued-at) рядом, чтобы проверять TTL при присвоении
            cookie()->queue(cookie()->make($cookie, $code, $ttlDays * 24 * 60, null, null, false, false, false, 'Lax'));
            cookie()->queue(cookie()->make($cookie.'_iat', (string) now()->timestamp, $ttlDays * 24 * 60, null, null, false, false, false, 'Lax'));
        }

        return $next($request);
    }
}
