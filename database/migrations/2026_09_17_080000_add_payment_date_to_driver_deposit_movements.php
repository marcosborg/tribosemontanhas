<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_deposit_movements', function (Blueprint $table) {
            $table->date('payment_date')->nullable()->after('payment_method');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('driver_deposit_movements', function (Blueprint $table) {
            $table->dropIndex(['payment_date']);
            $table->dropColumn('payment_date');
        });
    }
};
