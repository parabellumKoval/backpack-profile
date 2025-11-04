<?php

namespace Backpack\Profile\app\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $r)
    {
        $r->validate(['email' => ['required','email']]);

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

        $url = \Settings::get('profile.reset_password_redirect', '/') . '?newpassword=true&t=' . $token . '&email=' . $input['email'];

        return redirect($url);
    }
}
