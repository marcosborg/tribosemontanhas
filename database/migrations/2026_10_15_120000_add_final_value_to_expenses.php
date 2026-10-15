<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['vehicle_expenses', 'company_expenses'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->decimal('final_value', 15, 2)->nullable());
        }
    }

    public function down(): void
    {
        foreach (['vehicle_expenses', 'company_expenses'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('final_value'));
        }
    }
};
