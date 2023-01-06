<?php

namespace Backpack\Profile\app\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

use Backpack\Profile\app\Models\Profile;

/**
 * @group Auth
 *
 * APIs for authentication
 */
class RegisterController extends Controller
{
    /**
     * Register user
     * 
     * @bodyParam name string required User name.
     * @bodyParam email string required User email.
     * @bodyParam password string required Password.
     * @bodyParam password_confirmation string required Password confirmation.
     */
    public function __invoke(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules);

        if ($validator->fails())
          return response()->json($validator->messages(), 400);
        
        if($request->referrer_code) {
          $referrer = Profile::where('referrer_code', $request->referrer_code)->first();
        } 

        try {
          $user = Profile::create([
            'login' => $request->email,
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'password' => Hash::make($request->password),
            'email' => $request->email,
            'referrer_id' => isset($referrer) && $referrer? $referrer->id: null,
            'referrer_code' => Str::random(8)
          ]);
        }catch(\Extension) {
          return response()->json('error');
        }

        if($this->login($request->email, $request->password)){
          $request->session()->regenerate();
          return response()->json($user);
        }else {
          return response()->json('User was registered but not logged in', 400);
        }
    }

    private function login($email, $password) {
      if (Auth::guard('profile')->attempt([
        'email' => $email,
        'password' => $password
      ], true)) {
        return true;
      }else {
        return false;
      }
    }

    private $rules = [
        'firstname' => 'required|string|max:255',
        'lastname' => 'required|string|max:255',
        'email' => 'required|string|email|unique:ak_profiles,email',
        'password' => 'required|string|min:6|confirmed',
        'referrer_code' => 'nullable|string'
    ];
}
