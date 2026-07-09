<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vehicle_items', 'vehicle_type')) {
            Schema::table('vehicle_items', function (Blueprint $table) {
                $table->string('vehicle_type')->default('fleet')->after('suspended');
            });
        }

        Schema::table('car_tracks', function (Blueprint $table) {
            if (! Schema::hasColumn('car_tracks', 'vehicle_item_id')) {
                $table->unsignedBigInteger('vehicle_item_id')->nullable()->after('tvde_week_id');
                $table->foreign('vehicle_item_id')->references('id')->on('vehicle_items');
            }

            if (! Schema::hasColumn('car_tracks', 'driver_id')) {
                $table->unsignedBigInteger('driver_id')->nullable()->after('vehicle_item_id');
                $table->foreign('driver_id')->references('id')->on('drivers');
            }

            if (! Schema::hasColumn('car_tracks', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('driver_id');
                $table->foreign('company_id')->references('id')->on('companies');
            }

            if (! Schema::hasColumn('car_tracks', 'classification_status')) {
                $table->string('classification_status')->default('driver')->after('company_id');
                $table->index('classification_status');
            }

            if (! Schema::hasColumn('car_tracks', 'classification_reason')) {
                $table->string('classification_reason')->nullable()->after('classification_status');
            }
        });

        Schema::table('company_parks', function (Blueprint $table) {
            if (! Schema::hasColumn('company_parks', 'source_type')) {
                $table->string('source_type')->nullable()->after('fleet_management');
                $table->index('source_type');
                $table->unique(['tvde_week_id', 'company_id', 'source_type'], 'company_parks_week_company_source_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_parks', function (Blueprint $table) {
            if (Schema::hasColumn('company_parks', 'source_type')) {
                $table->dropUnique('company_parks_week_company_source_unique');
                $table->dropIndex(['source_type']);
                $table->dropColumn('source_type');
            }
        });

        Schema::table('car_tracks', function (Blueprint $table) {
            if (Schema::hasColumn('car_tracks', 'classification_reason')) {
                $table->dropColumn('classification_reason');
            }

            if (Schema::hasColumn('car_tracks', 'classification_status')) {
                $table->dropIndex(['classification_status']);
                $table->dropColumn('classification_status');
            }

            if (Schema::hasColumn('car_tracks', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }

            if (Schema::hasColumn('car_tracks', 'driver_id')) {
                $table->dropForeign(['driver_id']);
                $table->dropColumn('driver_id');
            }

            if (Schema::hasColumn('car_tracks', 'vehicle_item_id')) {
                $table->dropForeign(['vehicle_item_id']);
                $table->dropColumn('vehicle_item_id');
            }
        });

        if (Schema::hasColumn('vehicle_items', 'vehicle_type')) {
            Schema::table('vehicle_items', function (Blueprint $table) {
                $table->dropColumn('vehicle_type');
            });
        }
    }
};
