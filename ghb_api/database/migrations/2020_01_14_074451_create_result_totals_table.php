<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResultTotalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('result_totals', function (Blueprint $table) {
            $table->bigIncrements('id');
            // FK: Result_ID
            // $table->bigInteger('result_id')->unsigned()->index();
            // $table->foreign('result_id')->references('id')->on('results');

            // Polymorphic 1-to-Many with SO_KPI | Project_KPI
            $table->bigInteger('mappable_id')->unsigned();
            $table->string('mappable_type');

            // FK: Period_ID
            $table->integer('period_id')->nullable();
            // $table->bigInteger('period_id')->unsigned()->index();
            // $table->foreign('period_id')->references('id')->on('appraisal_period');

            // FK: Result_Threshold_Group_ID
            $table->integer('result_threshold_group_id')->nullable();
            // $table->bigInteger('result_threshold_group_id')->unsigned()->index();
            // $table->foreign('result_threshold_group_id')->references('id')->on('result_threshold_group');
            
            $table->tinyInteger('form_type');
            $table->decimal('result_score', 5, 2);
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
        Schema::dropIfExists('result_totals');
    }
}
