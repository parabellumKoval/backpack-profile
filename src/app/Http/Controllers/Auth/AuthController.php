<?php

// src/app/Http/Controllers/Auth/AuthController.php
namespace Backpack\Profile\app\Http\Controllers\Auth;

use Backpack\Profile\app\Services\WpPasswordChecker;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Backpack\Settings\Facades\Settings;
use Backpack\Profile\app\Models\Profile;

class AuthController extends Controller
{
    private $FULL_RESOURCE = '';
    private $TINY_RESOURCE = '';
    private $REFERRAL_RESOURCE = '';
    private $PROFILE_MODEL = '';

    protected WpPasswordChecker $wpPasswords;

    public function __construct(WpPasswordChecker $wpPasswords) {
      $this->FULL_RESOURCE = config('backpack.profile.full_resource', 'Backpack\Profile\app\Http\Resources\ProfileFullResource');
      $this->TINY_RESOURCE = config('backpack.profile.tiny_resource', 'Backpack\Profile\app\Http\Resources\ProfileTinyResource');
      $this->REFERRAL_RESOURCE = config('backpack.profile.referral_resource', 'Backpack\Profile\app\Http\Resources\ProfileReferralResource');
      $this->PROFILE_MODEL = config('backpack.profile.profile_model', 'Backpack\Profile\app\Models\Profile');

      $this->wpPasswords = $wpPasswords;
    }

    protected function userModel(): string
    {
        return config('auth.providers.users.model') ?? \App\Models\User::class;
    }

    protected function profileModel(): string
    {
        return config('backpack.profile.profile_model') ?? Backpack\Profile\app\Models\Profile::class;
    }

    public function register(Request $r)
    {
        if (!Settings::get('profile.users.allow_registration', true)) {
            return response()->json(['message' => 'Registration disabled'], 403);
        }

        $data = $r->validate([
            'name'     => ['nullable','string','max:255'],
            'email'    => ['required','email','max:255','unique:'.(new ($this->userModel()))->getTable()],
            'password' => ['required', Password::defaults()],
        ]);

        $userClass = $this->userModel();
        $user = new $userClass();
        $user->name  = $data['name'] ?? null;
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->save();

        // Default event
        event(new Registered($user));

        // Выдать token (Bearer) — удобно для Postman/Nuxt
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => $user->only(['id','name','email','email_verified_at']),
            'token' => $token,
        ], 201);
    }

    public function login(Request $r)
    {
        $r->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        $userClass = $this->userModel();
        $user = $userClass::where('email', $r->email)->first();

        // if (!$user || !Hash::check($r->password, $user->password)) {
        //     return response()->json(['message' => 'Invalid credentials'], 401);
        // }

        // --- НАЧАЛО -- С ПРОВЕРКОЙ WP пароля
        if (!$user) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $passwordOk = false;
        $viaWp      = false;

        // 1) пробуем обычный Laravel-хэш
        if (Hash::check($r->password, $user->password)) {
            $passwordOk = true;                           
        } elseif ($this->wpPasswords->check($r->password, $user->password)) {
            // 2) если не прошёл Laravel, пробуем как WP-хэш
            $passwordOk = true;                           
            $viaWp      = true;                           
        }

        if (! $passwordOk) {                              
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // если успешно залогинились через WP-хэш — перехэшируем в Laravel
        if ($viaWp) {                                     
            $user->password = Hash::make($r->password);   
            $user->save();                                
        }
        // --- КОНЕЦ -- С ПРОВЕРКОЙ WP пароля


        if (\Settings::get('profile.users.require_email_verification', true)) {
            $verified = method_exists($user, 'hasVerifiedEmail')
                ? $user->hasVerifiedEmail()
                : !empty($user->email_verified_at);

            if (!$verified) {
                return response()->json([
                    'message' => 'Email not verified',
                    'code'    => 'email_unverified',
                ], 403);
            }
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $r)
    {
        // отозвать текущий токен
        $r->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    public function me(Request $r)
    {
        return response()->json($r->user());
    }

    public function changePassword(Request $r)
    {
        $r->validate([
            'current_password' => ['required'],
            'password'         => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $r->user();

        // if (!Hash::check($r->current_password, $user->password)) {
        //     return response()->json(['message' => 'Current password is incorrect'], 422);
        // }

        // --- НАЧАЛО изменённого блока проверки текущего пароля ----------- //
        $currentOk = false;

        // 1) пробуем как Laravel-хэш
        if (Hash::check($r->current_password, $user->password)) {
            $currentOk = true;                                   
        }
        // 2) если не совпало — пробуем как WP-хэш
        elseif ($this->wpPasswords->check($r->current_password, $user->password)) {
            $currentOk = true;                                                   
        }

        if (! $currentOk) {                                                     
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }
        // --- КОНЕЦ изменённого блока проверки текущего пароля ------------- //

        $user->password = Hash::make($r->password);
        $user->save();

        return response()->json(['ok' => true]);
    }

    public function changeEmail(Request $r)
    {
        $user = $r->user();

        $data = $r->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique($user->getTable(), 'email')->ignore($user->getKey()),
            ],
            'password' => ['required'],
        ]);

        if (!Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        if (strcasecmp($data['email'], $user->email) === 0) {
            return response()->json(['message' => 'Email is unchanged'], 422);
        }

        $requiresVerification = Settings::get('profile.users.require_email_verification', true)
            && $user instanceof MustVerifyEmail;

        $user->email = $data['email'];

        if ($requiresVerification) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($requiresVerification && method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'ok'   => true,
            'user' => $user->fresh()->only(['id', 'name', 'email', 'email_verified_at']),
        ]);
    }


    public function validateReferralCode(string $code)
    {
        $exists = Profile::where('referral_code', $code)->exists();

        return response()->json($exists);
    }

    public function getReferrals(Request $r) {
      $referrals = $r->user()->profile->referrals()->paginate(12);
      return $this->REFERRAL_RESOURCE::collection($referrals);
    //   return response()->json($referrals);
    }
}
