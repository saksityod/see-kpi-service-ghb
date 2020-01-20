<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Result;
use App\SoKpi;
use App\ProjectKpi;
use App\ResultTotal;
use Faker\Generator as Faker;

$factory->define(Result::class, function (Faker $faker) {
    return [
        'description' => $faker->paragraph,
        'mappable_id' => $faker->randomElement($array = array (SoKpi::all()->random()->id, ProjectKpi::all()->random()->id)),
        'mappable_type' => $faker->randomElement($array = array ('App\SoKpi','App\ProjectKpi')),
        // 'period_id' => $faker->numberBetween($min = 1, $max = 6),
		'result_total_id' => ResultTotal::all()->random()->id,
        'result_threshold_group_id' => $faker->numberBetween($min = 1, $max = 5),
        'value_target' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'value_forecast' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'value_actual' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'percent_achievement' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'percent_forecast' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'weight_percent' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'weight_score' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'color_code' => $faker->hexcolor,
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});
