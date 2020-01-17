<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name','255');
            $table->mediumText('objective')->nullable();
            $table->integer('org_id')->nullable();
            $table->date('date_start');
            $table->date('date_end');
            $table->string('value','255');
            $table->mediumText('risk');
            $table->integer('emp_id')->nullable();
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
        Schema::dropIfExists('projects');
    }
}
