<?php

namespace Backpack\Profile\Facades;

use Illuminate\Support\Facades\Facade;

class Profile extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Profile';
    }
}
