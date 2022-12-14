<?php

namespace Backpack\Profile\app\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;

use Illuminate\Http\Request;
use App\Rules\EquallyPassword;

use Backpack\Profile\app\Models\Profile;

class ProfileController extends BaseController
{
    // public function index(Request $request) {
    //   return view('account.index');
    // }

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
