<?php
namespace Backpack\Profile\app\Models\Concerns;

trait ResolvesUserModel
{
    protected function userModelFqn(): string
    {
        // можно хранить в config/backpack/profile.php
        return config('backpack.profile.user_model', \App\Models\User::class);
    }
}
