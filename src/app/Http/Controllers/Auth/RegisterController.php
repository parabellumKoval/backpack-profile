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
      // GET PROFILE MODEL
      $profile_model = config('backpack.profile.profile_model', 'Backpack\Profile\app\Models\Profile');  

      // GET ONLY ALLOWED FIELDS
      $data = $request->only($profile_model::getFieldKeys('fieldsForRegistration'));
      
      // VALIDETE
      $validator = Validator::make($data, $profile_model::getRules(null, 'fieldsForRegistration'));

      if($validator->fails())
        return response()->json($validator->messages(), 400);
        
      // GET REFERRER
      if($request->referrer_code) {
        $referrer = $profile_model::where('referrer_code', $request->referrer_code)->first();
      } 

      // TRY TO CREATE USER
      try {
        $profile = new $profile_model;
        $profile->login = $data['email'];
        $profile->referrer_id = isset($referrer) && $referrer? $referrer->id: null;
        $profile->referrer_code = Str::random(8);

        foreach($data as $field_name => $field_value){

          $field = $profile_model::$fieldsForRegistration[$field_name] ?? $profile_model::$fieldsForRegistration[$field_name.'.*'];
          
          if(isset($field['hidden']) && $field['hidden'])
            continue;

          if(isset($field['hash']) && $field['hash'])
            $field_value = Hash::make($field_value);
          
          if(isset($field['store_in'])) {
            $field_old_value = $profile->{$field['store_in']};
            $field_old_value[$field_name] = $field_value;
            $profile->{$field['store_in']} = $field_old_value;
          }else {
            $profile->{$field_name} = $field_value;
          }
        }

        $profile->save();
      }
      catch(\Exception $e)
      {
        return response()->json($e->getMessage(), 400);
      }

        // try {
        //   $user = $profile_model::create([
        //     'login' => $request->email,
        //     'firstname' => $request->firstname,
        //     'lastname' => $request->lastname,
        //     'password' => Hash::make($request->password),
        //     'email' => $request->email,
        //     'referrer_id' => isset($referrer) && $referrer? $referrer->id: null,
        //     'referrer_code' => Str::random(8)
        //   ]);
        // }catch(\Extension) {
        //   return response()->json('error');
        // }

        if($this->login($request->email, $request->password)){
          $request->session()->regenerate();
          return response()->json($profile);
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
