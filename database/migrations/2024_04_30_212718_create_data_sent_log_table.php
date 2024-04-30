<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateDataSentLogTable extends Migration
{
    public function up()
    {
        Schema::create('data_sent_log', function (Blueprint $table) {
            $table->id();
            $table->date('data_date')->nullable();
            $table->date('sent_date');
            $table->boolean('data_sent')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('data_sent_log');
    }
}
