<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_items', function (Blueprint $table) {
            $table->date('green_card_expires_at')->nullable()->after('suspended');
            $table->date('private_conditions_expires_at')->nullable()->after('green_card_expires_at');
            $table->date('inspection_expires_at')->nullable()->after('private_conditions_expires_at');
            $table->date('dua_expires_at')->nullable()->after('inspection_expires_at');
            $table->date('fire_extinguisher_expires_at')->nullable()->after('dua_expires_at');
            $table->date('emel_expires_at')->nullable()->after('fire_extinguisher_expires_at');
            $table->date('cartrack_expires_at')->nullable()->after('emel_expires_at');
        });

        Schema::create('pending_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['completed_at', 'due_date']);
        });

        $permissionId = DB::table('permissions')->where('title', 'pending_access')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'title' => 'pending_access',
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

    public function down(): void
    {
        Schema::dropIfExists('pending_tasks');

        Schema::table('vehicle_items', function (Blueprint $table) {
            $table->dropColumn([
                'green_card_expires_at',
                'private_conditions_expires_at',
                'inspection_expires_at',
                'dua_expires_at',
                'fire_extinguisher_expires_at',
                'emel_expires_at',
                'cartrack_expires_at',
            ]);
        });

        $permissionId = DB::table('permissions')->where('title', 'pending_access')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
