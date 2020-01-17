<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\SoKpi;
use App\So;
use Faker\Generator as Faker;

$factory->define(SoKpi::class, function (Faker $faker) {
    return [
        'so_id' => So::all()->random()->id,
        'perspective_criteria_id' => $faker->numberBetween($min = 1, $max = 6),
        'name' => $faker->sentence,
        'item_id' => $faker->numberBetween($min = 1, $max = 47),
        'uom_id' => $faker->numberBetween($min = 1, $max = 6),
        'value_type_id' => $faker->numberBetween($min = 1, $max = 6),
        'function_type' => $faker->numberBetween($min = 1, $max = 4),
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});
