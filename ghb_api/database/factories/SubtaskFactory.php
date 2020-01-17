<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Subtask;
use App\Task;
use Faker\Generator as Faker;

$factory->define(Subtask::class, function (Faker $faker) {
    return [
        'task_id' => Task::all()->random()->id,
        'year_no' => $faker->numberBetween($min = 2017, $max = 2020),
        'month_no' => $faker->month($max = 'December'),
        'month_name' => $faker->monthName($max = 'December'),
        'weight_plan' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'weight_actual' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'budget_plan' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'budget_actual' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});
