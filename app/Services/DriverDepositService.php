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

    public function createRefund(DriverDeposit $deposit, int $tvdeWeekId, float $amount, ?string $description): DriverDepositMovement
    {
        return $this->createBalanceReducingMovement(
            $deposit,
            $tvdeWeekId,
            $amount,
            DriverDepositMovement::TYPE_REFUND,
            $description ?: 'Devolucao de caucao',
            true
        );
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

    public function availableBalance(DriverDeposit $deposit, ?int $tvdeWeekId = null): float
    {
        $query = DriverDepositMovement::where('driver_deposit_id', $deposit->id)
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
            ->orderByRaw('COALESCE(tvde_weeks.start_date, driver_deposit_movements.created_at)')
            ->orderBy('driver_deposit_movements.id')
            ->get();

        $balance = 0.0;
        foreach ($movements as $movement) {
            $amount = (float) $movement->amount;
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
        $movements = DriverDepositMovement::where('driver_deposit_id', $deposit->id)
            ->leftJoin('tvde_weeks', 'driver_deposit_movements.tvde_week_id', '=', 'tvde_weeks.id')
            ->orderByRaw('COALESCE(tvde_weeks.start_date, driver_deposit_movements.created_at)')
            ->orderBy('driver_deposit_movements.id')
            ->select('driver_deposit_movements.*')
            ->get();

        $balance = 0.0;

        foreach ($movements as $movement) {
            $amount = (float) $movement->amount;

            if (in_array($movement->type, [DriverDepositMovement::TYPE_INITIAL_CHARGE, DriverDepositMovement::TYPE_WEEKLY_CHARGE], true)) {
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
}
