<?php

namespace Backpack\Profile\app\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    protected array $allowed = ['google','facebook'];

    protected function userModel(): string
    {
        return config('auth.providers.users.model') ?? \App\Models\User::class;
    }

    // Вернуть URL авторизации провайдера (SPA редиректится туда на фронте)
    public function getRedirectUrl(Request $r, string $provider)
    {
        abort_unless(in_array($provider, $this->allowed, true), 404);

        $redirectUrl = $r->query('redirect_uri') // можно передавать из фронта
            ?? config("services.$provider.redirect")
            ?? url("/api/auth/oauth/{$provider}/callback");

        $url = Socialite::driver($provider)
            ->stateless()
            ->redirectUrl($redirectUrl)
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    // Callback: провайдер возвращает code → создаём/логиним юзера и выдаём token
    public function callback(Request $r, string $provider)
    {
        abort_unless(in_array($provider, $this->allowed, true), 404);

        $redirectUrl = $r->query('redirect_uri')
            ?? config("services.$provider.redirect")
            ?? url("/api/auth/oauth/{$provider}/callback");

        $oauthUser = Socialite::driver($provider)
            ->stateless()
            ->redirectUrl($redirectUrl)
            ->user();

        $email = $oauthUser->getEmail();
        $name  = $oauthUser->getName() ?: $oauthUser->getNickname();

        $userClass = $this->userModel();
        $user = $userClass::where('email', $email)->first();

        if (!$user) {
            // создаём нового
            $user = new $userClass();
            $user->name  = $name;
            $user->email = $email;
            // сгенерим случайный пароль (пользователь может сменить позже)
            $user->password = Hash::make(Str::random(32));
            // считаем email verified — провайдер валидировал почту
            if (method_exists($user, 'markEmailAsVerified')) {
                $user->markEmailAsVerified();
            }
            $user->save();
        }

        // (опционально) запишем линк в ak_social_accounts, если используешь такую таблицу

        $token = $user->createToken('api')->plainTextToken;

        // можно редиректнуть обратно во фронт с токеном в query (если удобно)
        if ($frontRedirect = $r->query('state_redirect') ?? config('profile.oauth_front_redirect')) {
            // В проде лучше прокидывать токен через secure-cookie/код обмена; здесь — для простоты
            return redirect()->to($frontRedirect.(str_contains($frontRedirect,'?')?'&':'?').'token='.$token);
        }

        return response()->json([
            'user'  => $user->only(['id','name','email','email_verified_at']),
            'token' => $token,
            'provider' => $provider,
        ]);
    }
}
