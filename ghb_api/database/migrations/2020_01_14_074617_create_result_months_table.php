<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResultMonthsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('result_months', function (Blueprint $table) {
            $table->bigIncrements('id');

            // FK: Result_ID
            $table->bigInteger('result_id')->unsigned()->index();
            $table->foreign('result_id')->references('id')->on('results');

            // FK: Period_ID
            // $table->integer('period_id')->nullable();
            // $table->bigInteger('period_id')->unsigned()->index();
            // $table->foreign('period_id')->references('id')->on('appraisal_period');
            
            $table->smallInteger('year_no');
            $table->tinyInteger('month_no');
            $table->string('month_name','50');
            $table->double('value_forecast', 15, 2)->default(0.0);
            $table->double('value_actual', 15, 2)->default(0.0);
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
        Schema::dropIfExists('result_months');
    }
}
