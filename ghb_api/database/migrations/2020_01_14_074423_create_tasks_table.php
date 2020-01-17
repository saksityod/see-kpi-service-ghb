<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            // FK: ActionPlan_ID
            $table->bigInteger('action_plan_id')->unsigned()->index();
            $table->foreign('action_plan_id')->references('id')->on('action_plans');

            // FK: ActualDesc_ID -> not sure if needed ?
            // $table->bigInteger('project_kpi_id')->unsigned()->index();
            // $table->foreign('project_kpi_id')->references('id')->on('project_kpis');
            
            $table->decimal('value', 15, 2)->default(0.0);
            $table->text('name')->nullable();
            $table->text('result')->nullable();
            $table->text('responsible')->nullable();
            $table->mediumText('description')->nullable();
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
        Schema::dropIfExists('tasks');
    }
}
