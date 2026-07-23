<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'weekly_km_allowance')) {
                $table->decimal('weekly_km_allowance', 12, 2)->nullable()->after('company_id');
            }
        });

        Schema::table('weekly_vehicle_expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'vehicle_item_id')) {
                $table->foreignId('vehicle_item_id')->nullable()->after('id')->constrained('vehicle_items')->nullOnDelete();
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->after('vehicle_item_id')->constrained('drivers')->nullOnDelete();
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'tvde_week_id')) {
                $table->foreignId('tvde_week_id')->nullable()->after('driver_id')->constrained('tvde_weeks')->nullOnDelete();
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'source')) {
                $table->string('source', 20)->nullable()->after('tvde_week_id');
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'status')) {
                $table->string('status', 30)->default('review')->after('source');
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'status_reason')) {
                $table->string('status_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'odometer_start')) {
                $table->decimal('odometer_start', 14, 2)->nullable()->after('status_reason');
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'odometer_end')) {
                $table->decimal('odometer_end', 14, 2)->nullable()->after('odometer_start');
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'distance_km')) {
                $table->decimal('distance_km', 14, 2)->nullable()->after('odometer_end');
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'original_filename')) {
                $table->string('original_filename')->nullable()->after('distance_km');
            }
            if (!Schema::hasColumn('weekly_vehicle_expenses', 'imported_at')) {
                $table->timestamp('imported_at')->nullable()->after('original_filename');
            }
        });

        if (!$this->indexExists('weekly_vehicle_expenses', 'weekly_mileage_vehicle_week_unique')) {
            Schema::table('weekly_vehicle_expenses', function (Blueprint $table) {
                $table->unique(['vehicle_item_id', 'tvde_week_id'], 'weekly_mileage_vehicle_week_unique');
            });
        }
        if (!$this->indexExists('weekly_vehicle_expenses', 'weekly_mileage_week_status_index')) {
            Schema::table('weekly_vehicle_expenses', function (Blueprint $table) {
                $table->index(['tvde_week_id', 'status'], 'weekly_mileage_week_status_index');
            });
        }

        if (!Schema::hasTable('weekly_vehicle_mileage_allocations')) {
            Schema::create('weekly_vehicle_mileage_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('weekly_vehicle_expense_id');
                $table->unsignedBigInteger('driver_id');
                $table->decimal('allocated_km', 14, 2);
                $table->decimal('allowance_km', 14, 2)->nullable();
                $table->decimal('extra_km', 14, 2)->nullable();
                $table->boolean('is_manual')->default(false);
                $table->timestamps();
                $table->unique(['weekly_vehicle_expense_id', 'driver_id'], 'weekly_mileage_allocation_unique');
                $table->foreign('weekly_vehicle_expense_id', 'wm_alloc_expense_fk')->references('id')->on('weekly_vehicle_expenses')->cascadeOnDelete();
                $table->foreign('driver_id', 'wm_alloc_driver_fk')->references('id')->on('drivers')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_vehicle_mileage_allocations');

        Schema::table('weekly_vehicle_expenses', function (Blueprint $table) {
            $table->dropUnique('weekly_mileage_vehicle_week_unique');
            $table->dropIndex('weekly_mileage_week_status_index');
            $table->dropConstrainedForeignId('vehicle_item_id');
            $table->dropConstrainedForeignId('driver_id');
            $table->dropConstrainedForeignId('tvde_week_id');
            $table->dropColumn([
                'source', 'status', 'status_reason', 'odometer_start', 'odometer_end',
                'distance_km', 'original_filename', 'imported_at',
            ]);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('weekly_km_allowance');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])) > 0;
    }
};
