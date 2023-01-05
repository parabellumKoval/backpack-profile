<?php

namespace Backpack\Profile\app\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

use Illuminate\Support\Facades\Auth;

/**
 * @group Auth
 *
 * APIs for authentication
 */
class LoginController extends Controller
{
    /**
     * Login user
     * 
     * @bodyParam email string required User email.
     * @bodyParam password string required User password.
     */
    public function __invoke(Request $request)
    {
      $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
      ]);

      if (Auth::guard('profile')->attempt($credentials)) {
        $request->session()->regenerate();

        $user = Auth::guard('profile')->user();

        return response()->json($user);
      }

      return response()->json('The provided credentials do not match our records.', 400);
    }

    /**
     * Logout user
     * 
     */
    public function logout(Request $request)
    {
      Auth::guard('profile')->logout();

      return response()->json('Logout');
    }
}
