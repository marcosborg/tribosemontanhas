<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('driver_deposit_plans', function (Blueprint $table) {
            $table->foreignId('driver_deposit_id')->nullable()->unique()->constrained('driver_deposits');
        });
        Schema::table('driver_deposit_plan_items', function (Blueprint $table) {
            $table->string('kind')->nullable();
        });
        Schema::table('driver_deposit_movements', function (Blueprint $table) {
            // Stable source key survives reversal, so revalidation restores the same receipt.
            $table->string('source_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('driver_deposit_movements', fn (Blueprint $table) => $table->dropColumn('source_key'));
        Schema::table('driver_deposit_plan_items', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('driver_deposit_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_deposit_id');
        });
    }
};
