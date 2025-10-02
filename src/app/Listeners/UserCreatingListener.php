<?php

namespace Backpack\Profile\app\Listeners;

use Illuminate\Support\Facades\Hash;
use Backpack\Profile\app\Events\UserCreating;
 
class UserCreatingListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }
 
    /**
     * Handle the event.
     *
     * @param  Backpack\Profile\app\Events\UserCreating  $event
     * @return void
     */
    public function handle(UserCreating $event)
    {
      // Access the order using $event->order...
    //   $event->user->password = $event->user->password? Hash::make($event->user->password): null;
      $event->user->name = empty($event->user->name)? $this->generateLoginFromEmail($event->user->email): $event->user->name;
    }

    public function generateLoginFromEmail($email)
    {
        // 1. Получаем часть до @
        $baseLogin = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', strstr($email, '@', true)));

        // Если логин получился пустым (например, email начинается с неразрешённых символов), fallback:
        if (!$baseLogin) {
            $baseLogin = 'user';
        }

        $login = $baseLogin;
        $i = 1;

        // 2. Проверяем на уникальность
        while (\Profile::userModel()::where('name', $login)->exists()) {
            $login = $baseLogin . $i;
            $i++;
        }

        return $login;
    }

    // public function generateUniqueReferralCode() {
    //   $code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz'), 0, 12);

    //   if(Profile::where('referrer_code', $code)->first()) {
    //       return $this->generateUniqueReferralCode();
    //   }

    //   return $code;
    // }
}