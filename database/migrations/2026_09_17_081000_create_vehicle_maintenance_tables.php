<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_item_id')->constrained('vehicle_items')->cascadeOnDelete();
            $table->string('type');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('interval_km');
            $table->unsignedInteger('last_service_km')->nullable();
            $table->date('last_service_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_item_id', 'type'], 'vehicle_maintenance_schedule_unique');
        });

        Schema::create('vehicle_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_maintenance_schedule_id');
            $table->foreignId('vehicle_item_id')->constrained('vehicle_items')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('performed_km');
            $table->date('performed_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('vehicle_maintenance_schedule_id', 'vehicle_maintenance_record_schedule_fk')
                ->references('id')->on('vehicle_maintenance_schedules')->cascadeOnDelete();
            $table->index(['vehicle_item_id', 'type', 'performed_at'], 'vehicle_maintenance_record_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_records');
        Schema::dropIfExists('vehicle_maintenance_schedules');
    }
};
