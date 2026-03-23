<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE combustion_transactions MODIFY transaction_date DATETIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE combustion_transactions MODIFY transaction_date DATE NULL');
    }
};
