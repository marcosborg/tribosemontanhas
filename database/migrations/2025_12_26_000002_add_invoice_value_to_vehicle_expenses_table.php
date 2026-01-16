<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->decimal('invoice_value', 15, 2)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->dropColumn('invoice_value');
        });
    }
};
