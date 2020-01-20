<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\ResultTotal;
use App\Result;
// use App\SoKpi;
// use App\ProjectKpi;
use App\So;
use App\Project;
use Faker\Generator as Faker;

$factory->define(ResultTotal::class, function (Faker $faker) {
    return [
        // 'result_id' => Result::all()->random()->id,
		// 'mappable_id' => $faker->randomElement($array = array (SoKpi::all()->random()->id, ProjectKpi::all()->random()->id)),
        // 'mappable_type' => $faker->randomElement($array = array ('App\SoKpi','App\ProjectKpi')),
        'spmapable_id' => $faker->randomElement($array = array (So::all()->random()->id, Project::all()->random()->id)),
        'spmapable_type' => $faker->randomElement($array = array ('App\So','App\Project')),
        'period_id' => $faker->numberBetween($min = 1, $max = 6),
        'result_threshold_group_id' => $faker->numberBetween($min = 1, $max = 4),
        'form_type' => $faker->numberBetween($min = 1, $max = 4),
        'result_score' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = 100),
        'color_code' => $faker->hexcolor,
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});