<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('revenue_summary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id');
            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->string('period');
            $table->timestamps();
            $table->index(['pharmacy_id', 'period']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('revenue_summary');
    }
};
