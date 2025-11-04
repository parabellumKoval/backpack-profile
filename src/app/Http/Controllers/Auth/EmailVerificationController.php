<?php

// src/app/Http/Controllers/Auth/EmailVerificationController.php
namespace Backpack\Profile\app\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Auth\Events\Verified;

class EmailVerificationController extends Controller
{
    public function send(Request $r)
    {
        if ($r->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified'], 200);
        }
        $r->user()->sendEmailVerificationNotification();
        return response()->json(['ok' => true]);
    }

    // Подтверждение по подписанной ссылке (без необходимости быть залогиненным)
    public function verify(Request $r, $id, $hash)
    {
        $userModel = config('auth.providers.users.model') ?? \App\Models\User::class;
        $user = $userModel::findOrFail($id);

        if (! hash_equals((string)$hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified'], 200);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        if ($redirectUrl = $this->buildVerificationRedirectUrl($r, $user, (string) $hash)) {
            return redirect()->away($redirectUrl);
        }

        return response()->json(['ok' => true]);
    }

    public function sendForEmail(Request $r)
    {
        $data = $r->validate(['email' => ['required','email']]);

        $userModel = config('auth.providers.users.model') ?? \App\Models\User::class;
        $user = $userModel::where('email', $data['email'])->first();

        if ($user) {
            // если уже верифицирован — просто вернём ok (не раскрываем состояние)
            if (method_exists($user, 'hasVerifiedEmail') ? !$user->hasVerifiedEmail() : empty($user->email_verified_at)) {
                $user->sendEmailVerificationNotification();
            }
        }

        // Чтобы не раскрывать, существует ли email, всегда отвечаем одинаково.
        return response()->json(['ok' => true]);
    }

    protected function buildVerificationRedirectUrl(Request $request, $user, string $hash): ?string
    {
        $baseUrl = \Settings::get('profile.email_verify_redirect', config('profile.email_verify_redirect'));

        if (empty($baseUrl)) {
            return null;
        }

        $params = array_filter([
            'id' => $user->getKey(),
            'hash' => $hash,
            'expires' => $request->query('expires'),
            'signature' => $request->query('signature'),
            'verified' => 1,
        ], static fn ($value) => ! is_null($value) && $value !== '');

        if (empty($params)) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query($params);
    }
}
