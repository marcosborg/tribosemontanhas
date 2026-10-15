<?php

namespace App\Services;

use App\Models\DriverDeposit;
use App\Models\DriverDepositMovement;
use App\Models\TvdeWeek;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverDepositService
{
    public function syncPlannedMovements(DriverDeposit $deposit, array $tvdeWeekIds): void
    {
        if ($deposit->plan()->exists()) {
            throw ValidationException::withMessages(['plan' => 'Edite as prestações na caução associada.']);
        }
        $tvdeWeekIds = array_values(array_unique(array_filter(array_map('intval', $tvdeWeekIds))));

        if ($tvdeWeekIds === []) {
            throw ValidationException::withMessages([
                'tvde_weeks' => 'Selecione pelo menos uma semana para debitar a caucao.',
            ]);
        }

        DB::transaction(function () use ($deposit, $tvdeWeekIds) {
            $deposit->refresh();

            DriverDepositMovement::where('driver_deposit_id', $deposit->id)
                ->whereIn('type', [
                    DriverDepositMovement::TYPE_INITIAL_CHARGE,
                    DriverDepositMovement::TYPE_WEEKLY_CHARGE,
                ])
                ->delete();

            $remaining = round((float) $deposit->total_amount, 2);

            if ($remaining > 0 && (float) $deposit->weekly_amount <= 0 && (float) $deposit->initial_payment <= 0) {
                throw ValidationException::withMessages([
                    'weekly_amount' => 'Defina um pagamento inicial ou um valor semanal para completar a caucao.',
                ]);
            }

            $weeks = TvdeWeek::whereIn('id', $tvdeWeekIds)
                ->orderBy('start_date')
                ->get();

            $this->createPlannedMovements($deposit, $weeks, $remaining);
            $this->recalculateBalances($deposit);
            $this->updateStatus($deposit);
        });
    }

    public function createInternalDebit(DriverDeposit $deposit, int $tvdeWeekId, float $amount, ?string $description): DriverDepositMovement
    {
        return $this->createBalanceReducingMovement(
            $deposit,
            $tvdeWeekId,
            $amount,
            DriverDepositMovement::TYPE_INTERNAL_DEBIT,
            $description ?: 'Abatimento a caucao',
            false
        );
    }

    public function createRefund(DriverDeposit $deposit, int $tvdeWeekId, float $amount, ?string $description, string $source): DriverDepositMovement
    {
        return $this->saveRefund($deposit, $tvdeWeekId, $amount, $description, $source);
    }

    public function updateRefund(DriverDepositMovement $movement, int $tvdeWeekId, float $amount, ?string $description, string $source): DriverDepositMovement
    {
        abort_unless($movement->type === DriverDepositMovement::TYPE_REFUND, 404);
        return $this->saveRefund($movement->deposit, $tvdeWeekId, $amount, $description, $source, $movement);
    }

    private function saveRefund(DriverDeposit $deposit, int $tvdeWeekId, float $amount, ?string $description, string $source, ?DriverDepositMovement $movement = null): DriverDepositMovement
    {
        return DB::transaction(function () use ($deposit, $tvdeWeekId, $amount, $description, $source, $movement) {
            $deposit = DriverDeposit::lockForUpdate()->findOrFail($deposit->id);
            if ($movement) $movement = $deposit->movements()->lockForUpdate()->findOrFail($movement->id);
            if (!isset(DriverDepositMovement::REFUND_SOURCE_SELECT[$source])) {
                throw ValidationException::withMessages(['refund_source' => 'Selecione a origem da devolução.']);
            }
            // Validated statements are snapshots: require reversal before changing their entries.
            $weeks = $source === 'weekly' ? [$tvdeWeekId] : [];
            if ($movement && $movement->affects_statement) $weeks[] = $movement->tvde_week_id;
            if (\App\Models\CurrentAccount::where('driver_id', $deposit->driver_id)->whereIn('tvde_week_id', $weeks)->exists()) {
                throw ValidationException::withMessages(['tvde_week_id' => 'Anule a validação do extrato da semana antes de alterar esta devolução e volte a validar depois.']);
            }
            $excludeId = $movement->id ?? null;
            $summary = $this->refundSummary($deposit, $excludeId, $tvdeWeekId);
            $allSummary = $this->refundSummary($deposit, $excludeId);
            $limit = min($summary[$source]['remaining'], $allSummary[$source]['remaining'],
                $this->availableBalance($deposit, $tvdeWeekId, $excludeId), $this->availableBalance($deposit, null, $excludeId));
            $cents = DriverDepositInstallmentService::cents($amount);
            if ($cents <= 0 || abs($amount * 100 - $cents) > 0.00001 || $cents > DriverDepositInstallmentService::cents($limit)) {
                throw ValidationException::withMessages(['amount' => 'O valor deve ter até duas casas decimais e não pode exceder o saldo disponível desta origem (' . number_format(max(0, $limit), 2, ',', '.') . ' €).']);
            }
            $movement = $movement ?: new DriverDepositMovement([
                'driver_deposit_id' => $deposit->id, 'driver_id' => $deposit->driver_id,
                'company_id' => $deposit->company_id, 'created_by' => auth()->id(), 'type' => DriverDepositMovement::TYPE_REFUND,
            ]);
            $movement->fill([
                'tvde_week_id' => $tvdeWeekId, 'amount' => $amount, 'refund_source' => $source,
                'description' => $description ?: 'Devolução de caução', 'affects_statement' => $source === 'weekly',
            ])->save();
            $this->recalculateBalances($deposit);
            $this->updateStatus($deposit);
            return $movement->refresh();
        });
    }

    public function refundSummary(DriverDeposit $deposit, ?int $excludeId = null, ?int $weekId = null): array
    {
        $summary = [];
        foreach (DriverDepositMovement::REFUND_SOURCE_SELECT as $source => $label) {
            $summary[$source] = ['label' => $label, 'received' => 0, 'refunded' => 0, 'remaining' => 0];
        }
        $summary['unclassified_refunds'] = 0;
        $summary['other_debits'] = 0;
        $integrated = $deposit->plan()->exists();
        $weekStart = $weekId ? TvdeWeek::findOrFail($weekId)->getRawOriginal('start_date') : null;
        $movements = $deposit->movements()->with(['plan_item', 'tvde_week'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->get();
        foreach ($movements as $entry) {
            if ($weekStart && $entry->tvde_week && $entry->tvde_week->getRawOriginal('start_date') > $weekStart) continue;
            $source = null;
            if ($integrated && $entry->type === 'payment') $source = $entry->plan_item->kind ?? null;
            if (!$integrated && $entry->type === 'initial_charge') $source = 'initial';
            if (!$integrated && $entry->type === 'weekly_charge') $source = 'weekly';
            if (isset(DriverDepositMovement::REFUND_SOURCE_SELECT[$source])) $summary[$source]['received'] += DriverDepositInstallmentService::cents($entry->amount);
            if ($entry->type === 'refund') {
                if (isset(DriverDepositMovement::REFUND_SOURCE_SELECT[$entry->refund_source])) {
                    $summary[$entry->refund_source]['refunded'] += DriverDepositInstallmentService::cents($entry->amount);
                } else {
                    $summary['unclassified_refunds'] += (float) $entry->amount;
                }
            }
            if (in_array($entry->type, ['internal_debit', 'writeoff'])) $summary['other_debits'] += (float) $entry->amount;
        }
        foreach (array_keys(DriverDepositMovement::REFUND_SOURCE_SELECT) as $source) {
            $summary[$source]['received'] /= 100;
            $summary[$source]['refunded'] /= 100;
            $summary[$source]['remaining'] = round($summary[$source]['received'] - $summary[$source]['refunded'], 2);
        }
        return $summary;
    }

    public function statementMovementsForWeek(int $driverId, int $companyId, int $tvdeWeekId): Collection
    {
        return DriverDepositMovement::with('deposit')
            ->where('driver_id', $driverId)
            ->where('company_id', $companyId)
            ->where('tvde_week_id', $tvdeWeekId)
            ->where('affects_statement', true)
            ->whereIn('type', [
                DriverDepositMovement::TYPE_INITIAL_CHARGE,
                DriverDepositMovement::TYPE_WEEKLY_CHARGE,
                DriverDepositMovement::TYPE_REFUND,
            ])
            ->orderBy('id')
            ->get();
    }

    public function statementImpact(Collection $movements): float
    {
        return round($movements->sum(function (DriverDepositMovement $movement) {
            $amount = (float) $movement->amount;

            return $movement->type === DriverDepositMovement::TYPE_REFUND ? $amount : -$amount;
        }), 2);
    }

    public function availableBalance(DriverDeposit $deposit, ?int $tvdeWeekId = null, ?int $excludeId = null): float
    {
        $integrated = $deposit->plan()->exists();
        $query = DriverDepositMovement::where('driver_deposit_id', $deposit->id)
            ->when($excludeId, fn ($q) => $q->where('driver_deposit_movements.id', '!=', $excludeId))
            ->leftJoin('tvde_weeks', 'driver_deposit_movements.tvde_week_id', '=', 'tvde_weeks.id')
            ->select('driver_deposit_movements.*');

        if ($tvdeWeekId) {
            $targetWeekStart = TvdeWeek::where('id', $tvdeWeekId)->value('start_date');

            if ($targetWeekStart) {
                $query->where(function ($weekQuery) use ($targetWeekStart) {
                    $weekQuery->whereNull('driver_deposit_movements.tvde_week_id')
                        ->orWhere('tvde_weeks.start_date', '<=', $targetWeekStart);
                });
            }
        }

        $movements = $query
            ->orderByRaw('COALESCE(tvde_weeks.start_date, driver_deposit_movements.payment_date, driver_deposit_movements.created_at)')
            ->orderBy('driver_deposit_movements.id')
            ->get();

        $balance = 0.0;
        foreach ($movements as $movement) {
            $amount = (float) $movement->amount;
            if ($integrated) {
                $balance += $this->realImpact($movement);
                continue;
            }
            $balance += in_array($movement->type, [DriverDepositMovement::TYPE_INITIAL_CHARGE, DriverDepositMovement::TYPE_WEEKLY_CHARGE], true)
                ? $amount
                : -$amount;
        }

        return round($balance, 2);
    }

    public function suggestedRefundWeekId(DriverDeposit $deposit): ?int
    {
        $driver = $deposit->driver;

        if ($driver && $driver->end_date) {
            $rawEndDate = $driver->getRawOriginal('end_date');
            $week = TvdeWeek::where('start_date', '<=', $rawEndDate)
                ->where('end_date', '>=', $rawEndDate)
                ->first();

            if ($week) {
                return $week->id;
            }
        }

        return (int) (session()->get('tvde_week_id') ?: TvdeWeek::orderByDesc('start_date')->value('id'));
    }

    private function createPlannedMovements(DriverDeposit $deposit, Collection $weeks, float $remaining): void
    {
        if ($remaining <= 0) {
            return;
        }

        if ($weeks->isEmpty()) {
            throw ValidationException::withMessages([
                'tvde_weeks' => 'As semanas selecionadas nao permitem completar a caucao.',
            ]);
        }

        $firstWeek = $weeks->first();
        $hasInitialCharge = DriverDepositMovement::where('driver_deposit_id', $deposit->id)
            ->where('type', DriverDepositMovement::TYPE_INITIAL_CHARGE)
            ->exists();
        $initial = $hasInitialCharge ? 0 : min((float) $deposit->initial_payment, $remaining);

        if ($initial > 0) {
            $this->createMovement($deposit, (int) $firstWeek->id, DriverDepositMovement::TYPE_INITIAL_CHARGE, $initial, 'Caucao - pagamento inicial', false);
            $remaining = round($remaining - $initial, 2);
        }

        if ($remaining <= 0) {
            return;
        }

        $weeklyAmount = (float) $deposit->weekly_amount;

        if ($weeklyAmount <= 0) {
            throw ValidationException::withMessages([
                'weekly_amount' => 'O valor semanal tem de ser superior a zero para completar a caucao.',
            ]);
        }

        if (($weeklyAmount * $weeks->count()) < $remaining) {
            throw ValidationException::withMessages([
                'tvde_weeks' => 'As semanas selecionadas nao chegam para completar o valor total da caucao.',
            ]);
        }

        foreach ($weeks as $week) {
            if ($remaining <= 0) {
                break;
            }

            $amount = min($weeklyAmount, $remaining);
            $this->createMovement($deposit, (int) $week->id, DriverDepositMovement::TYPE_WEEKLY_CHARGE, $amount, 'Caucao - pagamento semanal', true);
            $remaining = round($remaining - $amount, 2);
        }
    }

    private function createBalanceReducingMovement(DriverDeposit $deposit, int $tvdeWeekId, float $amount, string $type, string $description, bool $affectsStatement): DriverDepositMovement
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'O valor tem de ser superior a zero.',
            ]);
        }

        if ($amount > $this->availableBalance($deposit, $tvdeWeekId)) {
            throw ValidationException::withMessages([
                'amount' => 'O valor nao pode ser superior ao saldo disponivel da caucao.',
            ]);
        }

        return DB::transaction(function () use ($deposit, $tvdeWeekId, $amount, $type, $description, $affectsStatement) {
            $movement = $this->createMovement($deposit, $tvdeWeekId, $type, $amount, $description, $affectsStatement);
            $this->recalculateBalances($deposit);
            $this->updateStatus($deposit);

            return $movement;
        });
    }

    private function createMovement(DriverDeposit $deposit, int $tvdeWeekId, string $type, float $amount, string $description, bool $affectsStatement): DriverDepositMovement
    {
        return DriverDepositMovement::create([
            'driver_deposit_id' => $deposit->id,
            'driver_id' => $deposit->driver_id,
            'company_id' => $deposit->company_id,
            'tvde_week_id' => $tvdeWeekId,
            'type' => $type,
            'description' => $description,
            'amount' => $amount,
            'affects_statement' => $affectsStatement,
        ]);
    }

    public function recalculateBalances(DriverDeposit $deposit): void
    {
        $integrated = $deposit->plan()->exists();
        $movements = DriverDepositMovement::where('driver_deposit_id', $deposit->id)
            ->leftJoin('tvde_weeks', 'driver_deposit_movements.tvde_week_id', '=', 'tvde_weeks.id')
            ->orderByRaw('COALESCE(tvde_weeks.start_date, driver_deposit_movements.payment_date, driver_deposit_movements.created_at)')
            ->orderBy('driver_deposit_movements.id')
            ->select('driver_deposit_movements.*')
            ->get();

        $balance = 0.0;

        foreach ($movements as $movement) {
            $amount = (float) $movement->amount;

            if ($integrated) {
                $balance += $this->realImpact($movement);
            } elseif (in_array($movement->type, [DriverDepositMovement::TYPE_INITIAL_CHARGE, DriverDepositMovement::TYPE_WEEKLY_CHARGE], true)) {
                $balance += $amount;
            } else {
                $balance -= $amount;
            }

            $movement->balance_after = round($balance, 2);
            $movement->save();
        }
    }

    private function updateStatus(DriverDeposit $deposit): void
    {
        if ($deposit->plan()->exists()) {
            app(DriverDepositInstallmentService::class)->refreshState($deposit);
            return;
        }
        if ($deposit->status === DriverDeposit::STATUS_CLOSED) {
            return;
        }

        $charged = DriverDepositMovement::where('driver_deposit_id', $deposit->id)
            ->whereIn('type', [DriverDepositMovement::TYPE_INITIAL_CHARGE, DriverDepositMovement::TYPE_WEEKLY_CHARGE])
            ->sum('amount');

        $deposit->status = (float) $charged >= (float) $deposit->total_amount
            ? DriverDeposit::STATUS_COMPLETED
            : DriverDeposit::STATUS_ACTIVE;
        $deposit->save();
    }

    private function realImpact(DriverDepositMovement $movement): float
    {
        if (in_array($movement->type, ['payment', 'adjustment'], true)) return (float) $movement->amount;
        if (in_array($movement->type, ['refund', 'internal_debit', 'writeoff'], true)) return -(float) $movement->amount;
        return 0;
    }
}
