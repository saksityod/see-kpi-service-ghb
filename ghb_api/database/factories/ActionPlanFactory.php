<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\ActionPlan;
use App\Project;
use App\ProjectKpi;
use Faker\Generator as Faker;

$factory->define(ActionPlan::class, function (Faker $faker) {
    return [
        'project_id' => Project::all()->random()->id,
        'project_kpi_id' => ProjectKpi::all()->random()->id,
        'result_text' => $faker->paragraph,
        'forecast_text' => $faker->paragraph,
        'summary_text' => $faker->paragraph,
        'problem_text' => $faker->paragraph,
        'solution_text' => $faker->paragraph,
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});
