<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_tracks', function (Blueprint $table) {
            $table->string('source_filename')->nullable()->after('classification_reason');
            $table->unsignedInteger('source_row_number')->nullable()->after('source_filename');
            $table->string('source_fingerprint', 64)->nullable()->after('source_row_number');
            $table->string('service_description')->nullable()->after('source_fingerprint');
            $table->string('market_description')->nullable()->after('service_description');
            $table->unique('source_fingerprint', 'car_tracks_source_fingerprint_unique');
        });

        Schema::table('company_expenses', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('pay_to');
            $table->string('source_filename')->nullable()->after('source_type');
            $table->unsignedInteger('source_row_number')->nullable()->after('source_filename');
            $table->string('source_fingerprint', 64)->nullable()->after('source_row_number');
            $table->json('source_payload')->nullable()->after('source_fingerprint');
            $table->unique(['source_type', 'source_fingerprint'], 'company_expenses_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('company_expenses', function (Blueprint $table) {
            $table->dropUnique('company_expenses_source_unique');
            $table->dropColumn([
                'source_type',
                'source_filename',
                'source_row_number',
                'source_fingerprint',
                'source_payload',
            ]);
        });

        Schema::table('car_tracks', function (Blueprint $table) {
            $table->dropUnique('car_tracks_source_fingerprint_unique');
            $table->dropColumn([
                'source_filename',
                'source_row_number',
                'source_fingerprint',
                'service_description',
                'market_description',
            ]);
        });
    }
};
