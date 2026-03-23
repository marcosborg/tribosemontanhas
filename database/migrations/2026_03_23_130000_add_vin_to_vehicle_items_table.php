<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('vehicle_items', function (Blueprint $table) {
            $table->string('vin')->nullable()->after('license_plate');
        });
    }

    public function down()
    {
        Schema::table('vehicle_items', function (Blueprint $table) {
            $table->dropColumn('vin');
        });
    }
};
