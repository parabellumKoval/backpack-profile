<?php

namespace Backpack\Profile\app\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use ParabellumKoval\BackpackImages\Exceptions\ImageUploadException;
use ParabellumKoval\BackpackImages\Services\ImageUploader;
use ParabellumKoval\BackpackImages\Support\ImageUploadOptions;

class OAuthController extends Controller
{
    protected array $allowed = ['google','facebook'];

    public function __construct(protected ImageUploader $imageUploader)
    {
    }

    protected function userModel(): string
    {
        return config('auth.providers.users.model') ?? \App\Models\User::class;
    }

    // Вернуть URL авторизации провайдера (SPA редиректится туда на фронте)
    public function getRedirectUrl(Request $r, string $provider)
    {
        abort_unless(in_array($provider, $this->allowed, true), 404);

        $redirectUrl = config("services.$provider.redirect") ?? url("/api/auth/oauth/{$provider}/callback");
        // $key = \Settings::get('profile.referrals.url_param', 'ref');
        $key = 'referral_code';

        $referrerCode = $r->query($key, null);
        $redirectToFrontendUrl = $r->query('redirect_uri', null);
            
        $url = Socialite::driver($provider)
            ->stateless()
            ->with([
                'state' => "referrer_code={$referrerCode}&redirect_to={$redirectToFrontendUrl}"
            ])
            ->redirectUrl($redirectUrl)
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    // Callback: провайдер возвращает code → создаём/логиним юзера и выдаём token
    public function callback(Request $r, string $provider)
    {
        abort_unless(in_array($provider, $this->allowed, true), 404);

        $referrer_code = null;
        $redirect_to = null;

        // parse additional variables
        $state = $r->input('state');

        if(!empty($state)){
            parse_str($state, $result);

            if(!empty($result['referrer_code'])) {
                $referrer_code = $result['referrer_code'];
            } 

            if(!empty($result['redirect_to'])) {
                $redirect_to = $result['redirect_to'];
            } 
        }

        $oauthUser = Socialite::driver($provider)
            ->stateless()
            ->user();

        $email = $oauthUser->getEmail();
        $name  = $oauthUser->getName() ?: $oauthUser->getNickname();
        $remoteAvatar = $oauthUser->getAvatar();

        $userClass = $this->userModel();
        $user = $userClass::where('email', $email)->first();

        if (!$user) {
            // создаём нового
            $user = new $userClass();
            $user->name  = $name;
            $user->email = $email;
            $user->tempReferrerCode = $referrer_code;
            // сгенерим случайный пароль (пользователь может сменить позже)
            $user->password = Hash::make(Str::random(32));
            // считаем email verified — провайдер валидировал почту
            if (method_exists($user, 'markEmailAsVerified')) {
                $user->markEmailAsVerified();
            }
            $user->save();
        }

        if ($remoteAvatar && $user->profile) {
            $profile = $user->profile;
            $previousSource = data_get($profile->getMetaOther(), 'avatar_source');

            if (!$profile->avatar_url || $previousSource !== $remoteAvatar) {
                try {
                    $stored = $this->imageUploader->upload($remoteAvatar, new ImageUploadOptions(folder: 'avatars'));
                    $profile->avatar_url = $stored->url;
                    $profile->mergeMeta([
                        'other' => array_merge($profile->getMetaOther(), [
                            'avatar_source' => $remoteAvatar,
                            'avatar_path' => $stored->path,
                        ]),
                    ]);
                    $profile->save();
                } catch (ImageUploadException $exception) {
                    Log::warning(sprintf('Ошибка загрузки OAuth-аватара пользователя %s: %s', $user->id, $exception->getMessage()));
                }
            }
        }

        // (опционально) запишем линк в ak_social_accounts, если используешь такую таблицу

        $token = $user->createToken('api')->plainTextToken;

        // можно редиректнуть обратно во фронт с токеном в query (если удобно)
        if ($redirect_to ?? config('profile.oauth_front_redirect')) {
            // В проде лучше прокидывать токен через secure-cookie/код обмена; здесь — для простоты
            return redirect()->to($redirect_to.(str_contains($redirect_to,'?')?'&':'?').'token='.$token);
        }

        return response()->json([
            'user'  => $user->only(['id','name','email','email_verified_at']),
            'token' => $token,
            'provider' => $provider,
        ]);
    }
}
