<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActionPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('action_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            // FK: Project_ID
            $table->bigInteger('project_id')->unsigned()->index();
            $table->foreign('project_id')->references('id')->on('projects');
            // FK: Project_KPI_ID
            $table->bigInteger('project_kpi_id')->unsigned()->index();
            $table->foreign('project_kpi_id')->references('id')->on('project_kpis');
            $table->mediumText('result_text');
            $table->mediumText('forecast_text');
            $table->mediumText('summary_text');
            $table->mediumText('problem_text');
            $table->mediumText('solution_text');
            $table->string('created_by','50');
            $table->string('updated_by','50');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('action_plans');
    }
}
