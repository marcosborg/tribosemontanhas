<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE car_hires MODIFY end_date DATE NULL');
        }
    }

    public function down(): void
    {
        // Keep nullable: reverting must not discard existing open-ended rental periods.
    }
};
