<?php

namespace Backpack\Profile\app\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 */
class Profile extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Backpack\Profile\app\Services\Profile::class;
    }
}
