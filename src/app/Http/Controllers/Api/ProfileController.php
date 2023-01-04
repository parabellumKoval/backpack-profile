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
    // public function index(Request $request) {
    //   $profiles = Profile::all();
    //   return response()->json($profiles);
    // }

    public function show(Request $request) {
      $profile = Auth::guard('profile')->user();

      if(!$profile)
        return response()->json('Profile not found, access denied', 403);

      $profile = new ProfileFullResource($profile);

      return response()->json($profile);
    }

    public function update(Request $request) {

      $profile = Auth::guard('profile')->user();

      if(!$profile)
        return response()->json('Profile not found, access denied', 403);

      $data = $request->only(['firstname', 'lastname', 'phone', 'addresses', 'extras', 'photo']);

      $validator = Validator::make($data, [
        'firstname' => 'required|string|min:2|max:255',
        'lastname' => 'nullable|string|min:2|max:255',
        'phone' => 'nullable|string|min:2|max:255',
        'address[].country' => 'nullable|string|min:2|max:255',
        'address[].city' => 'nullable|string|min:2|max:255',
        'address[].state' => 'nullable|string|min:2|max:255',
        'address[].street' => 'nullable|string|min:2|max:255',
        'address[].apartment' => 'nullable|string|min:2|max:255',
        'address[].zip' => 'nullable|string|min:2|max:255'
      ]);
  
      if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
      }

      try {
        if(isset($data['firstname']))
          $profile->firstname = $data['firstname'];
        
        if(isset($data['lastname']))
          $profile->lastname = $data['lastname'];

        if(isset($data['phone']))
          $profile->phone = $data['phone'];

        if(isset($data['photo']))
          $profile->photo = $data['photo'];
        
        if(isset($data['addresses']))
          $profile->addresses = $data['addresses'];

        $profile->save();
      }catch(\Exception $e){
        return response()->json($e->getMessages(), 400);
      }

      return response()->json($profile);
    }


    public function referrals(Request $request) {
      $profile = Auth::guard('profile')->user();

      if(!$profile)
        return response()->json('Profile not found, access denied', 403);

      $referrals = $profile->referrals()->paginate(12);
      
      return ProfileTinyResource::collection($referrals);
    }

    // public function transactions(Request $request) {
    //   $transactions = Transaction::where('usermeta_id', \Auth::user()->usermeta->id)->where('is_completed', 1)->orWhere(function($query){
    //             $query->where('type', 'withdraw')
    //                   ->where('is_completed', 0);	      
    //   })->orderBy('created_at', 'desc')->paginate(20);
      
    //   $referrals = Usermeta::where('referrer_id', \Auth::user()->usermeta->id)->paginate(20);

    //   if($request->isJson)
    //     return response()->json([
    //     	'transactions' => $transactions, 
    //     	'referrals' => $referrals
    //     ]);
    //   else
    //     return view('account.transactions')->with('transactions', $transactions)->with('referrals', $referrals);
    // }


    public function addresess(Request $request){
      $user = \Auth::user();
          $usermeta = $user->usermeta;
      
      $usermeta->addressDetails = $request->input('address_details');
      
      $usermeta->save();
      
      return back();
    }
	
    public function edit(Request $request) {
      $user = \Auth::user();
      $usermeta = $user->usermeta;

      foreach($request->input() as $key => $value) {
        if($key == 'email')
          $user[$key] == $value;
        elseif($key != '_token') 
          $usermeta[$key] = $value;

        if($key == 'firstname')
          $user['name'] = $value;
      }

      $user->save();
      $usermeta->save();

      return back()->with('type', 'success')->with('message', 'Your account has been successfully updated!');
    }

    public function changePassword(Request $request) {
      $user = \Auth::user();
      $newPass = $request->input('password');
      $confirmPass = $request->input('password_confirmation');

      $validatedData = $request->validate([
          'password' => ['required', 'confirmed']
      ]);
      
      $user->password = \Hash::make($newPass);
      $user->save();

      return redirect('account')->with('type', 'success')->with('message', 'Your password has been successfully changed!');
    }

    // public function createTransaction(Request $request) {
    //   $transaction = new Transaction;

    //   $transaction->type = $request->input('transaction_type');
    //   $transaction->is_completed = 0;
    //   $transaction->change = $transaction->type == 'withdraw'? 0 - $request->input('transaction_change') : $request->input('transaction_change');
    //   $transaction->usermeta_id = \Auth::user()->usermeta->id;
    //   $transaction->description = 'Withdraw method: ' . $request->input('transaction_method') . "\r\n"
    //                              .'Requisite: ' . $request->input('transaction_requisites');

    //   $transaction->save();

    //   return back()->with('type', 'success')->with('message', 'Your withdrawal request successfully sent!');
    // }
}
