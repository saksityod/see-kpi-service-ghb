<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSoKpisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('so_kpis', function (Blueprint $table) {
            $table->bigIncrements('id');
            // FK: SO_ID
            $table->bigInteger('so_id')->unsigned()->index();
            $table->foreign('so_id')->references('id')->on('sos');
            $table->integer('perspective_criteria_id')->nullable();
            $table->string('name','255');
            $table->integer('item_id')->nullable();
            $table->integer('uom_id')->nullable();
            $table->integer('value_type_id')->nullable();
            $table->tinyInteger('function_type')->nullable();
            $table->boolean('is_active')->default(1);
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
        Schema::dropIfExists('so_kpis');
    }
}
