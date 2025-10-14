<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
 public function up()
{
    Schema::table('api_logs', function (Blueprint $table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
}

public function down()
{
    Schema::table('api_logs', function (Blueprint $table) {
        $table->dropColumn('user_id');
    });
}


    /**
     * Reverse the migrations.
     *
     * @return void
     */
}
