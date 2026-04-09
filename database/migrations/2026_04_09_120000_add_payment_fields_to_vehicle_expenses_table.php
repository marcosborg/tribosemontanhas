<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('invoice_value');
            $table->timestamp('paid_at')->nullable()->after('is_paid');
            $table->string('payment_reference')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'paid_at', 'payment_reference']);
        });
    }
};
