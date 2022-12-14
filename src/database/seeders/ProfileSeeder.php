<?php

namespace Backpack\Profile\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Profile\app\Models\Profile;

class ProfileSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
      Profile::factory()
          ->count(10)
          ->has(Profile::factory()->count(3), 'referrals')
          ->create();
    }
}
