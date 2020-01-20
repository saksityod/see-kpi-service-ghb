<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->mediumText('description');

            // Polymorphic 1-to-Many with SO_KPI | Project_KPI
            // $table->bigInteger('mappable_id')->unsigned();
            // $table->string('mappable_type');
			
			// FK : Result_Total_ID
            $table->bigInteger('result_total_id')->unsigned()->index();
            $table->foreign('result_total_id')->references('id')->on('result_totals');

            // Polymorphic 1-to-Many with SO_KPI | Project_KPI
            $table->bigInteger('mappable_id')->unsigned();
            $table->string('mappable_type');
            
            // FK: Period_ID
            //  $table->integer('period_id')->nullable();
            // $table->bigInteger('period_id')->unsigned()->index();
            // $table->foreign('period_id')->references('id')->on('appraisal_period');

            // FK: Result_Threshold_Group_ID
            $table->integer('result_threshold_group_id')->nullable();
            // $table->bigInteger('result_threshold_group_id')->unsigned()->index();
            // $table->foreign('result_threshold_group_id')->references('id')->on('result_threshold_group');
            
            $table->decimal('value_target', 15, 2)->nullable();
            $table->decimal('value_forecast', 15, 2)->nullable();
            $table->decimal('value_actual', 15, 2)->nullable();
            $table->decimal('percent_achievement', 15, 2)->nullable();
            $table->decimal('percent_forecast', 15, 2)->nullable();
            $table->decimal('weight_percent', 15, 2)->nullable();
            $table->decimal('weight_score', 15, 2)->nullable();
            $table->string('color_code','10');
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
        Schema::dropIfExists('results');
    }
}
