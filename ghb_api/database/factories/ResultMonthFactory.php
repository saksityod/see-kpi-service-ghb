<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\ResultMonth;
use App\Result;
use Faker\Generator as Faker;

$factory->define(ResultMonth::class, function (Faker $faker) {
    return [
        'result_id' => Result::all()->random()->id,
        // 'period_id' => $faker->numberBetween($min = 1, $max = 6),
        'year_no' => $faker->numberBetween($min = 2017, $max = 2020),
        'month_no' => $faker->month($max = 'December'),
        'month_name' => $faker->monthName($max = 'December'),
        'value_forecast' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'value_actual' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});
