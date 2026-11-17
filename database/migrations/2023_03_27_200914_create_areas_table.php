<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('address');
            $table->timestamps();
        });

        foreach (['clients', 'pharmacies'] as $referencing) {
            Schema::table($referencing, function (Blueprint $table) {
                $table->foreign('area_id')->references('id')->on('areas');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach (['clients', 'pharmacies'] as $referencing) {
            if (Schema::hasColumn($referencing, 'area_id')) {
                Schema::table($referencing, function (Blueprint $table) {
                    $table->dropForeign(['area_id']);
                });
            }
        }
        Schema::dropIfExists('areas');
    }
};
