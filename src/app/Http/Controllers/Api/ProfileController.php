<?php

namespace Backpack\Profile\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Rules\EquallyPassword;
use \Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use Backpack\Profile\app\Models\Profile;
use Backpack\Profile\app\Http\Resources\ProfileFullResource;
use Backpack\Profile\app\Http\Resources\ProfileTinyResource;

class ProfileController extends \App\Http\Controllers\Controller
{
  
    private $FULL_RESOURCE = '';
    private $TINY_RESOURCE = '';
    private $PROFILE_MODEL = '';

    public function __construct() {
      $this->FULL_RESOURCE = config('backpack.profile.full_resource', 'Backpack\Profile\app\Http\Resources\ProfileFullResource');
      $this->TINY_RESOURCE = config('backpack.profile.tiny_resource', 'Backpack\Profile\app\Http\Resources\ProfileTinyResource');
      $this->PROFILE_MODEL = config('backpack.profile.profile_model', 'Backpack\Profile\app\Models\Profile');
    }

    // public function test(Request $request) {
    //   //return $this->PROFILE_MODEL::getRules();
    //   return $this->update($request);
    // }

    public function show(Request $request) {
      $profile = Auth::guard('profile')->user();

      if(!$profile)
        return response()->json('Profile not found, access denied', 403);

      $profile = new $this->FULL_RESOURCE($profile);

      return response()->json($profile);
    }

    /**
     * Update profile data from request data
     * 
     * @param Request $request
     * @return Backpack/Profile/app/Models/Profile $profile
     */

    public function update(Request $request) {

      // Get user instance from AUTH guard
      $profile = Auth::guard('profile')->user();
      //$profile = $this->PROFILE_MODEL::find(1);

      if(!$profile)
        return response()->json('Profile not found, access denied', 403);

      // Get only allowed fields
      $data = $request->only($this->PROFILE_MODEL::getFieldKeys());

      // Apply validation rules to data
      $validator = Validator::make($data, $this->PROFILE_MODEL::getRules());
  
      if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
      }

      try {
        foreach($data as $field_name => $field_value){

          $field = $this->PROFILE_MODEL::$fields[$field_name] ?? $this->PROFILE_MODEL::$fields[$field_name.'.*'];
          
          if(isset($field['store_in'])) {
            $field_old_value = $profile->{$field['store_in']};
            $field_old_value[$field_name] = $field_value;
            $profile->{$field['store_in']} = $field_old_value;
          }else {
            $profile->{$field_name} = $field_value;
          }
        }

        $profile->save();
      }catch(\Exception $e){
        return response()->json($e->getMessage(), 400);
      }

      return response()->json($profile);
    }


    public function referrals(Request $request) {
      $profile = Auth::guard('profile')->user();

      if(!$profile)
        return response()->json('Profile not found, access denied', 403);

      $referrals = $profile->referrals()->paginate(12);
      
      return $this->TINY_RESOURCE::collection($referrals);
    }

    // public function changePassword(Request $request) {
    //   $user = \Auth::user();
    //   $newPass = $request->input('password');
    //   $confirmPass = $request->input('password_confirmation');

    //   $validatedData = $request->validate([
    //       'password' => ['required', 'confirmed']
    //   ]);
      
    //   $user->password = \Hash::make($newPass);
    //   $user->save();

    //   return redirect('account')->with('type', 'success')->with('message', 'Your password has been successfully changed!');
    // }

}
