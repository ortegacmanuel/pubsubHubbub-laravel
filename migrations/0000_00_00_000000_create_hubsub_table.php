<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHubsubTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hubsub', function (Blueprint $table) {
            $table->char('hashkey', 40);
            $table->primary('hashkey');
            $table->string('topic', 191)->index();
            $table->string('callback', 191)->index();
            $table->text('secret')->nullable();
            $table->dateTime('sub_start')->nullable();
            $table->dateTime('sub_end')->nullable();
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
        Schema::drop('hubsub');
    }
}