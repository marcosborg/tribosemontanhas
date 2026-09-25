<?php

namespace App\Services;

use App\Models\{CurrentAccount, DriverDeposit, DriverDepositMovement, DriverDepositPlan, DriverDepositPlanItem, TvdeWeek};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DriverDepositInstallmentService
{
    public static function cents($value): int
    {
        return (int) round((float) $value * 100);
    }

    public function save(Request $request, ?DriverDeposit $deposit = null): DriverDeposit
    {
        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'total_amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'status' => ['required', Rule::in(array_keys(DriverDeposit::STATUS_SELECT))],
            'notes' => ['nullable', 'string'],
            'initial_payments' => ['sometimes', 'array'],
            'initial_payments.*.id' => ['nullable', 'integer', 'distinct'],
            'initial_payments.*.amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'initial_payments.*.payment_date' => ['required', 'date_format:Y-m-d'],
            'initial_payments.*.payment_method' => ['nullable', 'string', 'max:255'],
            'weekly_payments' => ['sometimes', 'array'],
            'weekly_payments.*.id' => ['nullable', 'integer', 'distinct'],
            'weekly_payments.*.amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'weekly_payments.*.tvde_week_id' => ['required', 'integer', 'distinct', 'exists:tvde_weeks,id'],
        ]);
        $initial = $data['initial_payments'] ?? [];
        $weekly = $data['weekly_payments'] ?? [];
        if (array_sum(array_map(fn ($row) => self::cents($row['amount']), array_merge($initial, $weekly))) !== self::cents($data['total_amount'])) {
            $this->fail('total_amount', 'Os pagamentos iniciais e semanais têm de somar exatamente o valor total da caução.');
        }

        return DB::transaction(function () use ($deposit, $data, $initial, $weekly) {
            if ($deposit) {
                $deposit = DriverDeposit::lockForUpdate()->findOrFail($deposit->id);
                if ((int) $deposit->driver_id !== (int) $data['driver_id'] || (int) $deposit->company_id !== (int) $data['company_id']) {
                    $this->fail('driver_id', 'Não é possível mudar o motorista ou a empresa de uma caução registada.');
                }
            } else {
                $deposit = new DriverDeposit();
            }
            $deposit->fill(collect($data)->only(['driver_id', 'company_id', 'total_amount', 'status', 'notes'])->all());
            $deposit->initial_payment = array_sum(array_map(fn ($row) => self::cents($row['amount']), $initial)) / 100;
            $deposit->weekly_amount = 0; // Explicit installments are authoritative.
            $deposit->save();
            $plan = $deposit->plan()->firstOrCreate([], [
                'driver_id' => $deposit->driver_id, 'company_id' => $deposit->company_id,
                'initial_amount' => 0, 'weekly_amount' => 0, 'total_weeks' => 0, 'status' => 'active',
            ]);
            $items = $plan->items()->lockForUpdate()->get()->keyBy('id');
            $kept = [];
            foreach (['initial' => $initial, 'weekly' => $weekly] as $kind => $rows) {
                foreach ($rows as $index => $row) {
                    $field = $kind === 'initial' ? 'initial_payments' : 'weekly_payments';
                    $item = !empty($row['id']) ? $items->get($row['id']) : null;
                    if (!empty($row['id']) && (!$item || $item->kind !== $kind || in_array($item->id, $kept))) {
                        $this->fail($field, 'Parcela inválida para esta caução.');
                    }
                    if ($item) {
                        $same = self::cents($item->amount) === self::cents($row['amount']);
                        if ($kind === 'initial') {
                            $receipt = $item->movements()->where('type', 'payment')->firstOrFail();
                            $same = $same && $receipt->payment_date->format('Y-m-d') === $row['payment_date']
                                && (string) $receipt->payment_method === (string) ($row['payment_method'] ?? '');
                        } else {
                            $same = $same && (int) $item->tvde_week_id === (int) $row['tvde_week_id'];
                        }
                        if (!$same && $this->protectedItem($item, $deposit)) {
                            $this->fail($field, 'Não é possível alterar pagamentos recebidos ou prestações de semanas validadas.');
                        }
                        if ($same) {
                            $kept[] = $item->id;
                            continue;
                        }
                    }
                    if ($kind === 'weekly' && $this->validatedWeek($deposit, $row['tvde_week_id'])) {
                        $this->fail("{$field}.{$index}.tvde_week_id", 'A semana já foi validada. Anule a validação antes de alterar a prestação.');
                    }
                    $week = $kind === 'weekly' ? TvdeWeek::findOrFail($row['tvde_week_id']) : null;
                    $item = $item ?: new DriverDepositPlanItem(['plan_id' => $plan->id]);
                    $item->fill([
                        'kind' => $kind, 'amount' => $row['amount'], 'tvde_week_id' => $week->id ?? null,
                        'due_date' => $week ? $week->getRawOriginal('start_date') : $row['payment_date'],
                        'paid_amount' => $kind === 'initial' ? $row['amount'] : 0,
                        'status' => $kind === 'initial' ? 'paid' : 'pending',
                        'paid_at' => $kind === 'initial' ? $row['payment_date'] : null,
                    ])->save();
                    $item->movements()->updateOrCreate(['source_key' => $kind . ':' . $item->id], [
                        'driver_deposit_id' => $deposit->id, 'driver_id' => $deposit->driver_id,
                        'company_id' => $deposit->company_id, 'tvde_week_id' => $week->id ?? null,
                        'type' => $kind === 'initial' ? 'payment' : 'weekly_charge',
                        'amount' => $row['amount'], 'payment_date' => $row['payment_date'] ?? null,
                        'payment_method' => $row['payment_method'] ?? null, 'created_by' => auth()->id(),
                        'affects_statement' => $kind === 'weekly',
                        'description' => $kind === 'initial' ? 'Caução - pagamento inicial' : 'Caução - pagamento semanal',
                    ]);
                    $kept[] = $item->id;
                }
            }
            foreach ($items->except($kept) as $item) {
                if ($this->protectedItem($item, $deposit)) {
                    $this->fail('weekly_payments', 'Não é possível remover pagamentos recebidos ou prestações de semanas validadas.');
                }
                $item->movements()->delete();
                $item->delete();
            }
            $plan->update([
                'initial_amount' => $deposit->initial_payment, 'total_weeks' => count($weekly), 'notes' => $deposit->notes,
                'start_week_id' => TvdeWeek::whereIn('id', array_column($weekly, 'tvde_week_id'))->orderBy('start_date')->value('id'),
            ]);
            $this->refreshState($deposit);
            $this->syncSchedule($deposit);
            app(DriverDepositService::class)->recalculateBalances($deposit);
            return $deposit->refresh();
        });
    }

    public function validatedWeek(DriverDeposit $deposit, ?int $weekId): bool
    {
        return $weekId && CurrentAccount::where('driver_id', $deposit->driver_id)->where('tvde_week_id', $weekId)->exists();
    }

    private function protectedItem(DriverDepositPlanItem $item, DriverDeposit $deposit): bool
    {
        return $item->kind === 'initial' || self::cents($item->paid_amount) > 0 || $this->validatedWeek($deposit, $item->tvde_week_id);
    }

    public function syncSchedule(DriverDeposit $deposit): void
    {
        $plan = $deposit->plan()->first();
        if (!$plan) return;
        foreach ($plan->items()->where('kind', 'weekly')->get() as $item) {
            if ($this->validatedWeek($deposit, $item->tvde_week_id)) continue;
            $closed = $deposit->status === 'closed';
            $item->update(['status' => $closed ? 'cancelled' : (self::cents($item->paid_amount) >= self::cents($item->amount) ? 'paid' : 'pending')]);
            $item->movements()->where('type', 'weekly_charge')->update([
                'affects_statement' => !$closed && $plan->status !== 'paused' && self::cents($item->paid_amount) < self::cents($item->amount),
                'amount' => max(0, self::cents($item->amount) - self::cents($item->paid_amount)) / 100,
            ]);
        }
    }

    public function refreshState(DriverDeposit $deposit): void
    {
        $plan = $deposit->plan()->first();
        if (!$plan) return;
        $paid = self::cents($plan->items()->sum('paid_amount'));
        $complete = $paid >= self::cents($deposit->total_amount);
        if ($deposit->status !== 'closed') $deposit->update(['status' => $complete ? 'completed' : 'active']);
        if ($plan->status !== 'paused') $plan->update(['status' => $complete ? 'completed' : 'active']);
    }

    /** Receipts are created only for the movements included in the validated statement. */
    public function confirmWeek(int $driverId, int $companyId, int $weekId, array $movementIds): void
    {
        DB::transaction(function () use ($driverId, $companyId, $weekId, $movementIds) {
            if (!CurrentAccount::where('driver_id', $driverId)->where('tvde_week_id', $weekId)->exists()) return;
            $charges = DriverDepositMovement::whereIn('id', $movementIds)->where('driver_id', $driverId)
                ->where('company_id', $companyId)->where('tvde_week_id', $weekId)->where('type', 'weekly_charge')
                ->where('affects_statement', true)
                ->whereHas('deposit.plan')->get();
            foreach ($charges as $charge) {
                $item = DriverDepositPlanItem::lockForUpdate()->findOrFail($charge->driver_deposit_plan_item_id);
                $key = 'receipt:' . $item->id;
                $receipt = DriverDepositMovement::withTrashed()->where('source_key', $key)->first();
                if ($receipt && !$receipt->trashed()) continue;
                $amount = max(0, self::cents($item->amount) - self::cents($item->paid_amount));
                if (!$amount) continue;
                $receipt = $receipt ?: new DriverDepositMovement(['source_key' => $key]);
                $receipt->fill([
                    'driver_deposit_id' => $charge->driver_deposit_id, 'driver_deposit_plan_item_id' => $item->id,
                    'driver_id' => $driverId, 'company_id' => $companyId, 'tvde_week_id' => $weekId,
                    'type' => 'payment', 'amount' => $amount / 100, 'payment_date' => now()->toDateString(),
                    'payment_method' => 'Desconto semanal', 'description' => 'Caução - cobrança na validação semanal',
                    'affects_statement' => false, 'created_by' => auth()->id(), 'deleted_at' => null,
                ])->save();
                $item->update(['paid_amount' => $item->amount, 'status' => 'paid', 'paid_at' => now()]);
                $this->refreshState($charge->deposit);
                app(DriverDepositService::class)->recalculateBalances($charge->deposit);
            }
        });
    }

    public function reverseWeek(int $driverId, int $weekId): void
    {
        DB::transaction(function () use ($driverId, $weekId) {
            if (CurrentAccount::where('driver_id', $driverId)->where('tvde_week_id', $weekId)->exists()) return;
            $receipts = DriverDepositMovement::where('driver_id', $driverId)->where('tvde_week_id', $weekId)
                ->where('source_key', 'like', 'receipt:%')->lockForUpdate()->get();
            foreach ($receipts as $receipt) {
                $item = DriverDepositPlanItem::lockForUpdate()->findOrFail($receipt->driver_deposit_plan_item_id);
                $paid = max(0, self::cents($item->paid_amount) - self::cents($receipt->amount));
                $receipt->delete();
                $item->update(['paid_amount' => $paid / 100, 'status' => 'pending', 'paid_at' => null]);
                $this->refreshState($receipt->deposit);
                $this->syncSchedule($receipt->deposit);
                app(DriverDepositService::class)->recalculateBalances($receipt->deposit);
            }
        });
    }

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
