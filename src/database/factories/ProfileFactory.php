<?php

namespace Backpack\Profile\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Profile\app\Models\Profile;

class ProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Profile::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
      return [
        'login' => $this->faker->userName(),
        'email' => $this->faker->email(),
        'password' => $this->faker->password(),
        'firstname' => $this->faker->firstName(),
        'lastname' => $this->faker->lastName(),
        'phone' => $this->faker->phoneNumber(),
        'photo' => $this->faker->randomElement([
          'https://images.unsplash.com/photo-1600486913747-55e5470d6f40?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
          'https://images.unsplash.com/photo-1585807515950-bc46d934c28b?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
          'https://images.unsplash.com/photo-1559872553-c2607bb6259f?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
          'https://images.unsplash.com/photo-1495716868937-273203d5bb0c?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
          'https://images.unsplash.com/photo-1489985033736-3e81bb38baae?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
        ]),
        'referrer_id' => null,
        'referrer_code' => $this->faker->regexify('[A-Z]{3}[0-4]{3}'),
        // 'extras' => $this->faker->paragraph(2),
        'addresses' => [
          [
            'is_default' => 1,
            'country' => $this->faker->country(),
            'street' => $this->faker->streetName(),
            'apartment' => $this->faker->buildingNumber(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'zip' => $this->faker->postcode()	
          ]
        ],
      ];
    }

}
