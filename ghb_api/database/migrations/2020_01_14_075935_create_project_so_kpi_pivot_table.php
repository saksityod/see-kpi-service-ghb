<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectSoKpiPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project_so_kpi', function (Blueprint $table) {
            $table->bigInteger('project_id')->unsigned()->index();
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->bigInteger('so_kpi_id')->unsigned()->index();
            $table->foreign('so_kpi_id')->references('id')->on('so_kpis')->onDelete('cascade');
            $table->primary(['project_id', 'so_kpi_id']);
            // Extra Fields
            $table->string('created_by','50')->default('admin');
            $table->string('updated_by','50')->default('admin');
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
        Schema::dropIfExists('project_so_kpi');
    }
}
