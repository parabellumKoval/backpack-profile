<?php

namespace Backpack\Profile\app\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Backpack\Store\app\Services\Store;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $r)
    {
        $r->validate(['email' => ['required','email']]);

        $userModel = config('auth.providers.users.model') ?? User::class;
        $user = $userModel::where('email', $r->input('email'))->first();
        $storefront = Store::normalizeStorefrontCode(
            $r->input('storefront')
            ?? $r->header(Store::storefrontHeaderName())
            ?? $r->get(Store::storefrontRequestKey())
        );

        if ($user && method_exists($user, 'rememberPreferredStorefront')) {
            $user->rememberPreferredStorefront($storefront);
        }

        $status = Password::sendResetLink($r->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['ok' => true])
            : response()->json(['message' => __($status)], 422);
    }

    public function reset(Request $r)
    {
        $r->validate([
            'token'    => ['required'],
            'email'    => ['required','email'],
            'password' => ['required', PasswordRule::defaults(), 'confirmed'],
        ]);

        $status = Password::reset(
            $r->only('email','password','password_confirmation','token'),
            function ($user) use ($r) {
                $user->password = Hash::make($r->password);
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['ok' => true])
            : response()->json(['message' => __($status)], 422);
    }


    public function resetPasswordToken(Request $request, $token) {

        $input = $request->only('email');
        $userModel = config('auth.providers.users.model') ?? User::class;
        $user = !empty($input['email']) ? $userModel::where('email', $input['email'])->first() : null;
        $storefront = $request->query('storefront');

        if (!$storefront && method_exists($user, 'preferredStorefrontCode')) {
            $storefront = $user->preferredStorefrontCode();
        }

        $defaultUrl = \Settings::get('profile.reset_password_redirect', config('profile.reset_password_redirect', '/'));
        $storefrontBaseUrl = method_exists($userModel, 'storefrontFrontendUrl')
            ? $userModel::storefrontFrontendUrl($storefront)
            : null;
        $baseUrl = $storefrontBaseUrl
            ? rtrim($storefrontBaseUrl, '/') . '/new-password'
            : $defaultUrl;

        $url = rtrim((string) $baseUrl, '/') . '?newpassword=true&t=' . $token . '&email=' . $input['email'];

        return redirect($url);
    }
}
