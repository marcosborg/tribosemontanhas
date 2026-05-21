<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_deposit_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('initial_amount', 15, 2)->default(0);
            $table->decimal('weekly_amount', 15, 2)->default(0);
            $table->unsignedInteger('total_weeks')->default(0);
            $table->foreignId('start_week_id')->nullable()->constrained('tvde_weeks')->nullOnDelete();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['driver_id', 'company_id']);
            $table->index(['status', 'start_week_id']);
        });

        Schema::create('driver_deposit_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('driver_deposit_plans')->cascadeOnDelete();
            $table->foreignId('tvde_week_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'status']);
            $table->index(['tvde_week_id', 'due_date']);
        });

        Schema::table('driver_deposit_movements', function (Blueprint $table) {
            $table->foreignId('driver_deposit_plan_item_id')->nullable()->after('driver_deposit_id')->constrained('driver_deposit_plan_items')->nullOnDelete();
            $table->string('payment_method')->nullable()->after('amount');
            $table->foreignId('created_by')->nullable()->after('payment_method')->constrained('users')->nullOnDelete();
        });

        $permissionTitles = [
            'driver_deposit_plan_access',
            'driver_deposit_plan_create',
            'driver_deposit_plan_edit',
            'driver_deposit_plan_show',
            'driver_deposit_plan_delete',
            'driver_deposit_movement_access',
            'driver_deposit_movement_create',
            'driver_deposit_movement_edit',
            'driver_deposit_movement_show',
            'driver_deposit_movement_delete',
            'driver_deposit_reconciliation_access',
        ];

        foreach ($permissionTitles as $title) {
            $permissionId = DB::table('permissions')->where('title', $title)->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'title' => $title,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (DB::table('roles')->where('id', 1)->exists()) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => 1,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('driver_deposit_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('payment_method');
            $table->dropConstrainedForeignId('driver_deposit_plan_item_id');
        });

        Schema::dropIfExists('driver_deposit_plan_items');
        Schema::dropIfExists('driver_deposit_plans');
    }
};
