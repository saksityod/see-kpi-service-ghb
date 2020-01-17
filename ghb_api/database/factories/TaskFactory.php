<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Task;
use App\ActionPlan;
use Faker\Generator as Faker;

$factory->define(Task::class, function (Faker $faker) {
    return [
        'action_plan_id' => ActionPlan::all()->random()->id,
        'value' => $faker->randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL),
        'name' => $faker->paragraph,
        'result' => $faker->paragraph,
        'responsible' => $faker->paragraph,
        'description' => $faker->paragraph,
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});
