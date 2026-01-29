<?php

namespace App\Console\Commands;

use App\Models\VehicleExpense;
use Illuminate\Console\Command;

class BackfillVehicleExpenseNormalizedType extends Command
{
    protected $signature = 'vehicle-expenses:backfill-normalized-type';

    protected $description = 'Fill normalized_type for existing vehicle expenses without triggering model events.';

    public function handle(): int
    {
        $pending = VehicleExpense::whereNull('normalized_type')->count();

        if ($pending === 0) {
            $this->info('All vehicle expenses already have a normalized_type.');
            return 0;
        }

        $bar = $this->output->createProgressBar($pending);
        $bar->start();

        VehicleExpense::whereNull('normalized_type')
            ->orderBy('id')
            ->chunkById(100, function ($expenses) use ($bar) {
                foreach ($expenses as $expense) {
                    $expense->normalized_type = $this->determineNormalizedType($expense->expense_type);
                    $expense->saveQuietly();
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info('Normalized type backfill completed.');

        return 0;
    }

    private function determineNormalizedType(?string $expenseType): string
    {
        if (!$expenseType) {
            return 'other';
        }

        $key = mb_strtolower(trim($expenseType));

        return VehicleExpense::NORMALIZED_TYPE_MAP[$key] ?? 'other';
    }
}
