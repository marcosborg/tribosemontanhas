<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->string('normalized_type')->nullable()->index();
        });
    }

    public function down()
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->dropIndex(['normalized_type']);
            $table->dropColumn('normalized_type');
        });
    }
};
