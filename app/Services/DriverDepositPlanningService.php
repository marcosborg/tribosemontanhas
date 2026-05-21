<?php

namespace App\Services;

use App\Models\DriverDeposit;
use App\Models\DriverDepositMovement;
use App\Models\DriverDepositPlan;
use App\Models\DriverDepositPlanItem;
use App\Models\TvdeWeek;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverDepositPlanningService
{
    public function createPlan(array $data): DriverDepositPlan
    {
        return DB::transaction(function () use ($data) {
            $plan = DriverDepositPlan::create($data);
            $this->generateItems($plan);

            return $plan;
        });
    }

    public function updatePlan(DriverDepositPlan $plan, array $data): DriverDepositPlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->update($data);
            $this->generateItems($plan);

            return $plan->refresh();
        });
    }

    public function generateItems(DriverDepositPlan $plan): void
    {
        $hasPaidItems = $plan->items()
            ->where(function ($query) {
                $query->where('paid_amount', '>', 0)
                    ->orWhere('status', DriverDepositPlanItem::STATUS_PAID);
            })
            ->exists();

        if ($hasPaidItems) {
            throw ValidationException::withMessages([
                'plan' => 'Nao e possivel recalcular um plano com parcelas ja pagas.',
            ]);
        }

        $plan->items()->delete();

        $startWeek = TvdeWeek::find($plan->start_week_id);
        $startDate = $startWeek ? $startWeek->getRawOriginal('start_date') : null;

        $weeks = TvdeWeek::query()
            ->when($startDate, fn ($query) => $query->where('start_date', '>=', $startDate))
            ->orderBy('start_date')
            ->limit(max((int) $plan->total_weeks, 1) + 1)
            ->get();

        if ($weeks->isEmpty()) {
            throw ValidationException::withMessages([
                'start_week_id' => 'Selecione uma semana inicial valida.',
            ]);
        }

        $firstWeek = $weeks->first();

        if ((float) $plan->initial_amount > 0) {
            $this->createPlanItem($plan, $firstWeek, (float) $plan->initial_amount);
        }

        if ((float) $plan->weekly_amount <= 0 || (int) $plan->total_weeks <= 0) {
            return;
        }

        foreach ($weeks->take((int) $plan->total_weeks) as $week) {
            $this->createPlanItem($plan, $week, (float) $plan->weekly_amount);
        }
    }

    public function recordMovement(array $data): DriverDepositMovement
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'];
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor tem de ser superior a zero.',
                ]);
            }

            $deposit = $this->legacyDepositForMovement($data);
            $movement = DriverDepositMovement::create([
                'driver_deposit_id' => $deposit->id,
                'driver_id' => $data['driver_id'],
                'company_id' => $data['company_id'] ?? null,
                'tvde_week_id' => $data['tvde_week_id'] ?? null,
                'type' => $type,
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? null,
                'created_by' => auth()->id(),
                'affects_statement' => false,
            ]);

            if ($type === DriverDepositMovement::TYPE_PAYMENT) {
                $this->allocatePayment($movement);
            }

            app(DriverDepositService::class)->recalculateBalances($deposit);

            return $movement;
        });
    }

    public function reconciliationRows(array $filters = []): Collection
    {
        $plans = DriverDepositPlan::with(['driver', 'company', 'items'])
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['driver_id'] ?? null, fn ($query, $driverId) => $query->where('driver_id', $driverId))
            ->get();

        $driverIds = $plans->pluck('driver_id')->unique()->values();
        $movementTotals = DriverDepositMovement::query()
            ->whereIn('driver_id', $driverIds)
            ->whereIn('type', array_keys(DriverDepositMovement::REAL_TYPE_SELECT))
            ->selectRaw('driver_id, company_id, type, SUM(amount) as total')
            ->groupBy('driver_id', 'company_id', 'type')
            ->get()
            ->groupBy(fn ($movement) => $movement->driver_id . ':' . $movement->company_id);

        $rows = $plans
            ->groupBy(fn (DriverDepositPlan $plan) => $plan->driver_id . ':' . $plan->company_id)
            ->map(function (Collection $driverPlans, string $key) use ($movementTotals) {
                $first = $driverPlans->first();
                $movements = $movementTotals->get($key, collect());
                $realReceived = (float) $movements->whereIn('type', [
                    DriverDepositMovement::TYPE_PAYMENT,
                    DriverDepositMovement::TYPE_ADJUSTMENT,
                ])->sum('total');
                $refunds = (float) $movements->whereIn('type', [
                    DriverDepositMovement::TYPE_REFUND,
                    DriverDepositMovement::TYPE_WRITEOFF,
                ])->sum('total');
                $planned = (float) $driverPlans->flatMap->items->where('status', '!=', DriverDepositPlanItem::STATUS_CANCELLED)->sum('amount');
                $paid = (float) $driverPlans->flatMap->items->sum('paid_amount');
                $received = max($realReceived, $paid);

                return [
                    'driver' => $first->driver,
                    'company' => $first->company,
                    'planned' => round($planned, 2),
                    'received' => round($received, 2),
                    'debt' => round(max($planned - $paid, 0), 2),
                    'refunds' => round($refunds, 2),
                    'balance' => round($received - $refunds, 2),
                ];
            })
            ->values();

        if (!empty($filters['only_debt'])) {
            $rows = $rows->filter(fn ($row) => $row['debt'] > 0)->values();
        }

        if (!empty($filters['only_positive_balance'])) {
            $rows = $rows->filter(fn ($row) => $row['balance'] > 0)->values();
        }

        return $rows;
    }

    public function timeline(int $driverId, ?int $companyId = null): Collection
    {
        $items = DriverDepositPlanItem::with(['plan', 'tvde_week'])
            ->whereHas('plan', function ($query) use ($driverId, $companyId) {
                $query->where('driver_id', $driverId)
                    ->when($companyId, fn ($planQuery) => $planQuery->where('company_id', $companyId));
            })
            ->get()
            ->map(fn (DriverDepositPlanItem $item) => [
                'date' => optional($item->due_date)->format('Y-m-d'),
                'kind' => 'Previsto',
                'label' => DriverDepositPlanItem::STATUS_SELECT[$item->status] ?? $item->status,
                'amount' => (float) $item->amount,
                'week' => $item->tvde_week->start_date ?? '',
            ]);

        $movements = DriverDepositMovement::with('tvde_week')
            ->where('driver_id', $driverId)
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereIn('type', array_keys(DriverDepositMovement::REAL_TYPE_SELECT))
            ->get()
            ->map(fn (DriverDepositMovement $movement) => [
                'date' => optional($movement->created_at)->format('Y-m-d'),
                'kind' => 'Real',
                'label' => DriverDepositMovement::REAL_TYPE_SELECT[$movement->type] ?? $movement->type,
                'amount' => (float) $movement->amount,
                'week' => $movement->tvde_week->start_date ?? '',
            ]);

        return $items->concat($movements)->sortBy('date')->values();
    }

    private function createPlanItem(DriverDepositPlan $plan, TvdeWeek $week, float $amount): DriverDepositPlanItem
    {
        $rawDueDate = $week->getRawOriginal('start_date') ?: now()->toDateString();

        return DriverDepositPlanItem::create([
            'plan_id' => $plan->id,
            'tvde_week_id' => $week->id,
            'due_date' => Carbon::parse($rawDueDate)->toDateString(),
            'amount' => round($amount, 2),
            'status' => DriverDepositPlanItem::STATUS_PENDING,
        ]);
    }

    private function allocatePayment(DriverDepositMovement $movement): void
    {
        $remaining = round((float) $movement->amount, 2);
        $items = DriverDepositPlanItem::whereHas('plan', function ($query) use ($movement) {
                $query->where('driver_id', $movement->driver_id)
                    ->where('company_id', $movement->company_id);
            })
            ->whereIn('status', [
                DriverDepositPlanItem::STATUS_PENDING,
                DriverDepositPlanItem::STATUS_OVERDUE,
            ])
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            if ($remaining <= 0) {
                break;
            }

            $open = round((float) $item->amount - (float) $item->paid_amount, 2);
            if ($open <= 0) {
                continue;
            }

            $allocated = min($open, $remaining);
            $item->paid_amount = round((float) $item->paid_amount + $allocated, 2);
            $item->status = (float) $item->paid_amount >= (float) $item->amount
                ? DriverDepositPlanItem::STATUS_PAID
                : DriverDepositPlanItem::STATUS_PENDING;
            $item->paid_at = $item->status === DriverDepositPlanItem::STATUS_PAID ? now() : null;
            $item->save();

            if (!$movement->driver_deposit_plan_item_id) {
                $movement->driver_deposit_plan_item_id = $item->id;
                $movement->save();
            }

            $remaining = round($remaining - $allocated, 2);
        }
    }

    private function legacyDepositForMovement(array $data): DriverDeposit
    {
        return DriverDeposit::firstOrCreate(
            [
                'driver_id' => $data['driver_id'],
                'company_id' => $data['company_id'] ?? null,
                'status' => DriverDeposit::STATUS_ACTIVE,
            ],
            [
                'total_amount' => 0,
                'initial_payment' => 0,
                'weekly_amount' => 0,
                'notes' => 'Registo tecnico para movimentos reais de caucao.',
            ]
        );
    }
}
