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
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropForeign('clients_area_id_foreign');
            });
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'area_id',
                'street_name',
                'building_no',
                'floor_number',
                'flat_number',
                'is_main',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id');
            $table->foreign('clients_area_id_foreign')
                ->references('id')
                ->on('area');
            $table->type('area_id');
            $table->type('street_name');
            $table->type('building_no');
            $table->type('floor_number');
            $table->type('flat_number');
            $table->type('is_main');
        });
    }
};
