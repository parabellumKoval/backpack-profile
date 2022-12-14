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
        'photo' => $this->faker->imageUrl(640, 480, 'AVATAR', true),
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
