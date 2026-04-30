<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->string('id', 14)->primary();
            $table->unsignedBigInteger('user_id');
            $table->enum('gender', ['Male', 'Female']);
            $table->date('date_of_birth');
            $table->string('avatar_image')->default('default-avatar.jpg');
            $table->string('phone');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};
