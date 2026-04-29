<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('initial_payment', 15, 2)->default(0);
            $table->decimal('weekly_amount', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('driver_deposit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_deposit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tvde_week_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->boolean('affects_statement')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['driver_id', 'tvde_week_id']);
            $table->index(['company_id', 'type']);
        });

        $permissionTitles = [
            'driver_deposit_access',
            'driver_deposit_create',
            'driver_deposit_edit',
            'driver_deposit_show',
            'driver_deposit_delete',
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

            if (! DB::table('roles')->where('id', 1)->exists()) {
                continue;
            }

            $exists = DB::table('permission_role')
                ->where('role_id', 1)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $exists) {
                DB::table('permission_role')->insert([
                    'role_id' => 1,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_deposit_movements');
        Schema::dropIfExists('driver_deposits');
    }
};
