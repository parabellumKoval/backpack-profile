<?php

namespace Backpack\Profile\app\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Mail\Message;


use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
/**
 * @group Auth
 *
 * APIs for authentication
 */
class ResetPasswordController extends Controller
{
  public function resetPassword(Request $request, $token) {

    $input = $request->only('email');

    $url = config('backpack.profile.reset_password_redirect', '/new-password') . '/' . $token . '?email=' . $input['email'];

    return redirect($url);
  }

  public function forgotPassword(Request $request)
  {
      $input = $request->all();
      $rules = array(
          'email' => "required|email",
      );
      $validator = Validator::make($input, $rules);
      if ($validator->fails()) {
          $arr = array("status" => 400, "message" => $validator->errors()->first(), "data" => array());
      } else {
          try {
              $status = Password::sendResetLink($request->only('email'));

              return $status === Password::RESET_LINK_SENT
                ? \Response::json(array("status" => 200, "message" => trans($status), "data" => array()))
                : \Response::json(array("status" => 400, "message" => trans($status), "data" => array()));

          } catch (\Swift_TransportException $ex) {
              $arr = array("status" => 400, "message" => $ex->getMessage(), "data" => []);
          } catch (\Exception $ex) {
              $arr = array("status" => 400, "message" => $ex->getMessage(), "data" => []);
          }
      }
      return \Response::json($arr);
  }

  public function changePassword(Request $request) {
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password)
            ])->setRememberToken(Str::random(60));

            $user->save();

            event(new PasswordReset($user));
        }
    );

    return $status === Password::PASSWORD_RESET
                ? \Response::json(array("status" => 200, "message" => trans($status), "data" => array()))
                : \Response::json(array("status" => 400, "message" => trans($status), "data" => array()));
  } 

}