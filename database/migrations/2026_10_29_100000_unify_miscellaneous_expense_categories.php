<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            // Include soft-deleted entries so restoring one cannot restore an obsolete category.
            $aliases = ['Acessórios', 'Acessorios', 'Outros', 'Vários/ Outros', 'Vários / Outros', 'Vários /Outros', 'Varios/Outros'];
            foreach (['vehicle_expenses', 'company_expenses'] as $table) {
                DB::table($table)->whereIn('expense_type', $aliases)->update(['expense_type' => 'Vários/Outros']);
            }
            // The company workflow also keeps the category in the legacy name column.
            DB::table('company_expenses')->whereIn('name', $aliases)->update(['name' => 'Vários/Outros']);
        });
    }

    public function down(): void
    {
        // Merged categories cannot be split reliably; restore the pre-migration backup if required.
    }
};
