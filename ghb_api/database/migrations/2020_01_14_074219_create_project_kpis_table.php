<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectKpisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project_kpis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name','255');
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
        Schema::dropIfExists('project_kpis');
    }
}
