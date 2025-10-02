<?php

// src/app/Http/Controllers/Auth/AuthController.php
namespace Backpack\Profile\app\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Backpack\Settings\Facades\Settings;

class AuthController extends Controller
{
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

        // GET REFERRER
        // if($request->referrer_code) {
        //     $referrer = $this->profileModel()::where('referrer_code', $request->referrer_code)->first();
        // } 

        // // TRY TO CREATE USER
        // try {
        //     $profile = new $profile_model;
        //     $profile->login = $data['email'];
        //     $profile->referrer_id = isset($referrer) && $referrer? $referrer->id: null;
        //     $profile->referrer_code = Str::random(8);

        //     foreach($data as $field_name => $field_value){

        //     $field = $profile_model::$fieldsForRegistration[$field_name] ?? $profile_model::$fieldsForRegistration[$field_name.'.*'];
            
        //     if(isset($field['hidden']) && $field['hidden'])
        //         continue;

        //     if(isset($field['hash']) && $field['hash'])
        //         $field_value = Hash::make($field_value);
            
        //     if(isset($field['store_in'])) {
        //         $field_old_value = $profile->{$field['store_in']};
        //         $field_old_value[$field_name] = $field_value;
        //         $profile->{$field['store_in']} = $field_old_value;
        //     }else {
        //         $profile->{$field_name} = $field_value;
        //     }
        //     }

        //     $profile->save();
        // }

        event(new Registered($user));

        // Выдать token (Bearer) — удобно для Postman/Nuxt
        $token = $user->createToken('api')->plainTextToken;

        // Если нужно требовать email-верификацию — фронт может проверить $user->hasVerifiedEmail()
        if (\Settings::get('profile.users.require_email_verification', true)) {
            $user->sendEmailVerificationNotification();
        }

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

        if (!$user || !Hash::check($r->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }


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
            'user'  => $user->only(['id','name','email','email_verified_at']),
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

        if (!Hash::check($r->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->password = Hash::make($r->password);
        $user->save();

        return response()->json(['ok' => true]);
    }
}
