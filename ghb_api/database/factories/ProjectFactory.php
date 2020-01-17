<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Project;
use Faker\Generator as Faker;

$factory->define(Project::class, function (Faker $faker) {
    return [
        'name' => $faker->sentence,
        'objective' => $faker->paragraph,
        'org_id' => $faker->numberBetween($min = 1, $max = 8),
        'date_start' => $faker->dateTime($max = 'now', $timezone = 'Asia/Bangkok'),
        'date_end' => $faker->dateTime($max = '+ 10 years', $timezone = 'Asia/Bangkok'),
        'value' => $faker->sentence,
        'risk' => $faker->paragraph,
        'emp_id' => $faker->numberBetween($min = 1, $max = 34),
        'created_by' => 'Faker',
        'updated_by' => 'Faker'
    ];
});
