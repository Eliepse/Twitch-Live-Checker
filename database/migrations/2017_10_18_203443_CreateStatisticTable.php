<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStatisticTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('stats_requests', function (Blueprint $table) {
			$table->string('date')->primary();
			$table->unsignedSmallInteger('interval');
			$table->mediumInteger('amount');
			$table->smallInteger('average_exec_time');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('stats_requests');
	}
}
