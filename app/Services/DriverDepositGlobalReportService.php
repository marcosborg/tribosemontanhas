<?php

namespace App\Services;

use App\Models\{CurrentAccount, DriverDepositMovement};
use Illuminate\Support\Collection;

class DriverDepositGlobalReportService
{
    public const COLUMNS = [
        'driver_id' => 'ID motorista', 'name' => 'Motorista', 'companies' => 'Empresa(s)',
        'opening_total' => 'Saldo inicial do período',
        'initial_received' => 'Inicial — recebido', 'initial_refunded' => 'Inicial — devolvido', 'initial_balance' => 'Inicial — saldo final',
        'weekly_received' => 'Semanal — recebido', 'weekly_refunded' => 'Semanal — devolvido', 'weekly_balance' => 'Semanal — saldo final',
        'other_received' => 'Por classificar — recebido', 'other_refunded' => 'Por classificar — devolvido', 'other_balance' => 'Saldo não atribuído',
        'adjustments' => 'Ajustes no período', 'debits' => 'Abatimentos no período',
        'total_received' => 'Total recebido', 'total_refunded' => 'Total devolvido', 'total_balance' => 'Saldo total final',
    ];

    public const NOTES = 'Recebimentos e devoluções referem-se ao período selecionado; os saldos incluem o histórico até à data final. Inclui motoristas inativos e saldos liquidados. Nas cobranças semanais usa-se o início da semana TVDE; nos pagamentos iniciais usa-se a data de pagamento, com recurso à semana ou data de registo se não existir. Prestações futuras ou não validadas não são recebimentos. Devoluções sem origem, pagamentos sem classificação, ajustes e abatimentos ficam no saldo não atribuído. Saldo final = saldo inicial + recebido − devolvido + ajustes − abatimentos.';

    public function rows(string $from, string $to): Collection
    {
        $movements = DriverDepositMovement::with([
            'driver' => fn ($q) => $q->withTrashed(), 'company' => fn ($q) => $q->withTrashed(),
            'deposit' => fn ($q) => $q->withTrashed()->with('plan'), 'plan_item',
            'tvde_week' => fn ($q) => $q->withTrashed(),
        ])->get();
        $validated = CurrentAccount::whereIn('driver_id', $movements->pluck('driver_id')->unique())
            ->get(['driver_id', 'tvde_week_id'])->mapWithKeys(fn ($a) => [$a->driver_id . ':' . $a->tvde_week_id => true]);
        $realReceipts = $movements->where('type', 'payment')->pluck('driver_deposit_id')->flip();
        $rows = [];
        foreach ($movements as $movement) {
            $source = 'other';
            $kind = null;
            if ($movement->type === 'payment') {
                $source = in_array($movement->plan_item->kind ?? null, ['initial', 'weekly']) ? $movement->plan_item->kind : 'other';
                $kind = 'received';
            } elseif (in_array($movement->type, ['initial_charge', 'weekly_charge'])) {
                // Linked installments have separate receipt entries; never count both.
                if ($movement->deposit?->plan || $realReceipts->has($movement->driver_deposit_id)) continue;
                $source = $movement->type === 'initial_charge' ? 'initial' : 'weekly';
                if ($source === 'weekly' && !$validated->has($movement->driver_id . ':' . $movement->tvde_week_id)) continue;
                $kind = 'received';
            } elseif ($movement->type === 'refund') {
                $source = in_array($movement->refund_source, ['initial', 'weekly']) ? $movement->refund_source : 'other';
                $kind = 'refunded';
            } elseif ($movement->type === 'adjustment') {
                $kind = 'adjustments';
            } elseif (in_array($movement->type, ['internal_debit', 'writeoff'])) {
                $kind = 'debits';
            }
            if (!$kind) continue;
            $weekDate = $movement->tvde_week?->getRawOriginal('start_date');
            $paymentDate = $movement->payment_date?->format('Y-m-d');
            $date = substr((string) (($source === 'initial' && $kind === 'received' ? $paymentDate : null)
                ?: $weekDate ?: $paymentDate ?: $movement->getRawOriginal('created_at')), 0, 10);
            if (!$date || $date > $to) continue;
            $id = $movement->driver_id;
            if (!isset($rows[$id])) {
                $rows[$id] = array_fill_keys(array_keys(self::COLUMNS), 0);
                $rows[$id]['driver_id'] = $id;
                $rows[$id]['name'] = $movement->driver->name ?? 'Motorista #' . $id;
                $rows[$id]['companies'] = [];
            }
            $row = &$rows[$id];
            $company = $movement->company->name ?? ('Empresa #' . ($movement->company_id ?? '—'));
            $row['companies'][$company] = $company;
            $amount = DriverDepositInstallmentService::cents($movement->amount);
            $impact = in_array($kind, ['refunded', 'debits']) ? -$amount : $amount;
            $row[$source . '_balance'] += $impact;
            $row['total_balance'] += $impact;
            if ($date < $from) {
                $row['opening_total'] += $impact;
            } else {
                $row[in_array($kind, ['received', 'refunded']) ? $source . '_' . $kind : $kind] += $amount;
                if (in_array($kind, ['received', 'refunded'])) $row['total_' . $kind] += $amount;
            }
            unset($row);
        }
        return collect($rows)->map(function ($row) {
            $row['companies'] = implode(', ', $row['companies']);
            foreach (array_slice(array_keys(self::COLUMNS), 3) as $key) $row[$key] /= 100;
            return $row;
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }
}
