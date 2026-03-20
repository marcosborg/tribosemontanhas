<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('document_type')->nullable()->after('payment_vat');
            $table->string('emergency_contact')->nullable()->after('phone');
        });

        DB::table('drivers')
            ->whereNotNull('citizen_card')
            ->whereNull('document_type')
            ->update(['document_type' => 'cc']);
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'emergency_contact']);
        });
    }
};
