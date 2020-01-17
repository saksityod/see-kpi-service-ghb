<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\ProjectKpi;
use Faker\Generator as Faker;

$factory->define(ProjectKpi::class, function (Faker $faker) {
    return [
        'name' => $faker->sentence,
        'uom_id' => $faker->numberBetween($min = 1, $max = 8),
        'value_type_id' => $faker->numberBetween($min = 1, $max = 4),
        'function_type' => $faker->numberBetween($min = 1, $max = 5),
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});
