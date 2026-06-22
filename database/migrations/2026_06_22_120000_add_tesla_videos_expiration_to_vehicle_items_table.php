<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vehicle_items', 'tesla_videos_expires_at')) {
            Schema::table('vehicle_items', function (Blueprint $table) {
                $table->date('tesla_videos_expires_at')->nullable()->after('cartrack_expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicle_items', 'tesla_videos_expires_at')) {
            Schema::table('vehicle_items', function (Blueprint $table) {
                $table->dropColumn('tesla_videos_expires_at');
            });
        }
    }
};
