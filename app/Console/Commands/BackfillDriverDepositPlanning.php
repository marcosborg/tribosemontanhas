<?php

namespace App\Console\Commands;

use App\Models\DriverDeposit;
use App\Models\DriverDepositMovement;
use App\Models\DriverDepositPlan;
use App\Models\DriverDepositPlanItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillDriverDepositPlanning extends Command
{
    protected $signature = 'driver-deposits:backfill-planning {--force : Run without confirmation}';

    protected $description = 'Create deposit plans and plan items from the legacy driver_deposits data without deleting existing records.';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Create planning records from existing legacy deposits?', false)) {
            $this->warn('Operation cancelled.');
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        DriverDeposit::with(['movements.tvde_week'])->orderBy('id')->chunk(100, function ($deposits) use (&$created, &$skipped) {
            foreach ($deposits as $deposit) {
                $exists = DriverDepositPlan::where('notes', 'like', '%legacy driver_deposits #' . $deposit->id . '%')->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                DB::transaction(function () use ($deposit, &$created) {
                    $plannedMovements = $deposit->movements
                        ->whereIn('type', [
                            DriverDepositMovement::TYPE_INITIAL_CHARGE,
                            DriverDepositMovement::TYPE_WEEKLY_CHARGE,
                        ])
                        ->sortBy(fn ($movement) => optional($movement->tvde_week)->getRawOriginal('start_date') ?: $movement->created_at);

                    $firstMovement = $plannedMovements->first();
                    $weeklyCount = $plannedMovements->where('type', DriverDepositMovement::TYPE_WEEKLY_CHARGE)->count();
                    $plan = DriverDepositPlan::create([
                        'driver_id' => $deposit->driver_id,
                        'company_id' => $deposit->company_id,
                        'initial_amount' => (float) $deposit->initial_payment,
                        'weekly_amount' => (float) $deposit->weekly_amount,
                        'total_weeks' => $weeklyCount,
                        'start_week_id' => $firstMovement->tvde_week_id ?? null,
                        'status' => DriverDepositPlan::STATUS_ACTIVE,
                        'notes' => trim(($deposit->notes ? $deposit->notes . PHP_EOL : '') . 'Migrado de legacy driver_deposits #' . $deposit->id),
                    ]);

                    foreach ($plannedMovements as $movement) {
                        $rawDueDate = $movement->tvde_week ? $movement->tvde_week->getRawOriginal('start_date') : null;
                        $isPaid = $movement->type === DriverDepositMovement::TYPE_INITIAL_CHARGE;

                        DriverDepositPlanItem::create([
                            'plan_id' => $plan->id,
                            'tvde_week_id' => $movement->tvde_week_id,
                            'due_date' => $rawDueDate ?: optional($movement->created_at)->toDateString(),
                            'amount' => $movement->amount,
                            'paid_amount' => $isPaid ? $movement->amount : 0,
                            'status' => $isPaid ? DriverDepositPlanItem::STATUS_PAID : DriverDepositPlanItem::STATUS_PENDING,
                            'paid_at' => $isPaid ? $movement->created_at : null,
                        ]);
                    }

                    $totalPlanned = (float) $plan->items()->sum('amount');
                    $totalPaid = (float) $plan->items()->sum('paid_amount');

                    if ($totalPlanned > 0 && $totalPaid >= $totalPlanned) {
                        $plan->update(['status' => DriverDepositPlan::STATUS_COMPLETED]);
                    }

                    $created++;
                });
            }
        });

        $this->info("Backfill completed. Created: {$created}. Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
