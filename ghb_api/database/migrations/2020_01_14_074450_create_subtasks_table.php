<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubtasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subtasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            // FK: Task_ID
            $table->bigInteger('task_id')->unsigned()->index();
            $table->foreign('task_id')->references('id')->on('tasks');
            $table->smallInteger('year_no');
            $table->tinyInteger('month_no');
            $table->string('month_name','50');
            $table->double('weight_plan', 15, 2);
            $table->double('weight_actual', 15, 2);
            $table->double('budget_plan', 15, 2);
            $table->double('budget_actual', 15, 2);
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
        Schema::dropIfExists('subtasks');
    }
}
