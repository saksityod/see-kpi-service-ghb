<?php

use App\SoKpi;
use App\Project;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        /*  
            These shoud be in their own Seeder classes,
            for better feedback (when errors) in the CLI, 
            but I'm too lazy...

            @Rock
        */

		// !!! Order is important !!!
        $sos = factory(App\So::class, 300)->create();
        $so_kpis = factory(App\SoKpi::class, 300)->create();
        $projects = factory(App\Project::class, 300)->create();
        $project_kpis = factory(App\ProjectKpi::class, 300)->create();
        $action_plans = factory(App\ActionPlan::class, 300)->create();
        $tasks = factory(App\Task::class, 300)->create();
        $subtasks = factory(App\Subtask::class, 300)->create();
		$result_totals = factory(App\ResultTotal::class, 300)->create();
        $results = factory(App\Result::class, 300)->create();
        $result_months = factory(App\ResultMonth::class, 300)->create();
        

        /*
         *  @TODO
		 *
         *  Need to convert these guys to Laravel 5.1 syntax
         *  
         */

        // //  Pivot: Project <---> SO_KPI
        // $projects->each(function (App\Project $p) use ($so_kpis) {
        //     $p->so_kpis()->attach(
        //         $so_kpis->random(rand(1, 5))->pluck('id')->toArray()
        //     );
        // });

        // //  Pivot: Project <---> Project_KPI
        // $projects->each(function (App\Project $p) use ($project_kpis) {
        //     $p->project_kpis()->attach(
        //         $project_kpis->random(rand(1, 5))->pluck('id')->toArray()
        //     );
        // });

        /*
         *
         * ===================================================
         * 
         */

        Model::reguard();
    }
}
