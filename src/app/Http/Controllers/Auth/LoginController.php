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
        if (!Auth::guard('profile')->attempt($request->only('email', 'password'))) {
            throw new AuthenticationException();
        }

        $request->session()->regenerate();
        return Auth::guard('profile')->user();
    }

    /**
     * Logout user
     * 
     */
    public function logout(Request $request)
    {
      Auth::guard('profile')->logout();
    }
}
