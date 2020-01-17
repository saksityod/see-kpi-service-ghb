<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\So;
use Faker\Generator as Faker;

$factory->define(So::class, function (Faker $faker) {
    return [
        // 'seq_no' => $faker->numberBetween($min = 1, $max = 10),
        'name' => $faker->sentence,
        'abbr' => $faker->sentence,
        'color_code' => $faker->hexcolor,
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});
