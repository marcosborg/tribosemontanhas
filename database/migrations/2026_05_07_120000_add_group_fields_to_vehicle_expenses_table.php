<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->uuid('group_uuid')->nullable()->index()->after('pay_to');
            $table->string('group_label')->nullable()->after('group_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->dropColumn(['group_uuid', 'group_label']);
        });
    }
};
