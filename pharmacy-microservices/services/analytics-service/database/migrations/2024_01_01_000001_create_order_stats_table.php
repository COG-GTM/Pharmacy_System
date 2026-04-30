<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status');
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_stats');
    }
};
