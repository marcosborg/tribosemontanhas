<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            // Include soft-deleted entries so restoring one cannot restore an obsolete category.
            $aliases = ['Acessórios', 'Acessorios', 'Outros', 'Vários/ Outros', 'Vários / Outros', 'Vários /Outros', 'Varios/Outros'];
            $original = [];
            foreach (['vehicle_expenses', 'company_expenses'] as $table) {
                $original[$table] = DB::table($table)->whereIn('expense_type', $aliases)->get()->all();
            }
            $original['company_names'] = DB::table('company_expenses')->whereIn('name', $aliases)->get()->all();
            $directory = storage_path('app/private/category-backups');
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Cannot create category backup directory.');
            }
            $backup = $directory . '/categories-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
            if (file_put_contents($backup, json_encode($original, JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('Cannot save category backup.');
            }
            chmod($backup, 0600);
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
