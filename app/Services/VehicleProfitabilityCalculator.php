<?php

namespace App\Services;

use App\Http\Controllers\Traits\Reports;
use App\Models\CurrentAccount;
use App\Models\Driver;
use App\Models\ExpenseReimbursement;
use App\Models\Receipt;
use App\Models\TvdeWeek;
use App\Models\VehicleExpense;
use App\Models\VehicleItem;
use App\Models\VehicleUsage;
use Carbon\Carbon;

class VehicleProfitabilityCalculator
{
    use Reports;

    protected const PROFITABLE_NORMALIZED_TYPES = [
        'acquisition',
        'maintenance',
        'rent',
    ];

    /**
     * Cache in-memory per request to avoid recalculating week reports.
     *
     * @var array<int, array>
     */
    protected array $weekReports = [];

    public function computeWeekMetrics(VehicleItem $vehicleItem, TvdeWeek $tvdeWeek): array
    {
        $driver = $this->resolveDriverForWeek($vehicleItem, $tvdeWeek);

        if (!$driver) {
            return $this->emptyRow($tvdeWeek);
        }

        $rawResults = $this->getLiveDriverResults($vehicleItem, $tvdeWeek, $driver->id)
            ?? $this->getSnapshotResults($tvdeWeek, $driver->id);

        $results = $this->normalizeResults($rawResults);

        $iva = $this->calculateDriverIva($driver, $results['total']);
        $rf = $this->calculateDriverRf($driver, $results['total']);

        $adjustments = $this->sumCompanyAdjustments($results['adjustments_array']);

        $receipt = Receipt::where([
            'tvde_week_id' => $tvdeWeek->id,
            'driver_id'    => $driver->id,
        ])->latest()->first();

        $fuelTransactionsVat = $this->calculateFuelVat($results['fuel_transactions']);

        $vehicleExpenses = $this->calculateVehicleExpenses($vehicleItem, $tvdeWeek);

        $expenseReimbursementsValue = ExpenseReimbursement::where('vehicle_item_id', $vehicleItem->id)
            ->whereDate('date', '>=', $tvdeWeek->start_date)
            ->whereDate('date', '<=', $tvdeWeek->end_date)
            ->sum('value');

        $totalTreasury = ($results['total_net'] ?? 0)
            - ($results['car_track'] ?? 0)
            - ($results['fuel_transactions'] ?? 0)
            + ($adjustments ?? 0)
            - ($rf ?? 0)
            - ($receipt->amount_transferred ?? 0)
            - ($vehicleExpenses['treasury'] ?? 0)
            + ($expenseReimbursementsValue ?? 0);

        $totalTaxes = - ($results['vat_value'] ?? 0)
            + ($iva ?? 0)
            + ($fuelTransactionsVat ?? 0)
            + ($vehicleExpenses['vat'] ?? 0);

        $finalTotal = $totalTreasury + $totalTaxes;

        return [
            'week'  => $tvdeWeek,
            'year'  => Carbon::parse($tvdeWeek->start_date)->year,
            'month' => Carbon::parse($tvdeWeek->start_date)->month,

            'driver' => $driver,
            'results' => $rawResults ?? (object) [],
            'receipt' => $receipt,
            'adjustments' => $adjustments,
            'rf' => $rf,
            'iva' => $iva,
            'fuel_transactions_vat' => $fuelTransactionsVat,
            'vehicle_expenses_value' => $vehicleExpenses['treasury'] ?? 0,
            'vehicle_expenses_vat' => $vehicleExpenses['vat'] ?? 0,
            'expense_reimbursements_value' => $expenseReimbursementsValue,

            'total_treasury' => $totalTreasury,
            'total_taxes'    => $totalTaxes,
            'final_total'    => $finalTotal,
        ];
    }

    protected function resolveDriverForWeek(VehicleItem $vehicleItem, TvdeWeek $tvdeWeek): ?Driver
    {
        $usage = VehicleUsage::with('driver.contract_vat')
            ->where('vehicle_item_id', $vehicleItem->id)
            ->whereDate('start_date', '<=', $tvdeWeek->end_date)
            ->where(function ($q) use ($tvdeWeek) {
                $q->whereDate('end_date', '>=', $tvdeWeek->start_date)
                    ->orWhereNull('end_date');
            })
            ->whereHas('driver')
            ->first();

        return $usage?->driver;
    }

    protected function getLiveDriverResults(VehicleItem $vehicleItem, TvdeWeek $tvdeWeek, int $driverId)
    {
        if (!$vehicleItem->company_id) {
            return null;
        }

        if (!array_key_exists($tvdeWeek->id, $this->weekReports)) {
            $this->weekReports[$tvdeWeek->id] = $this->getWeekReport($vehicleItem->company_id, $tvdeWeek->id);
        }

        $report = $this->weekReports[$tvdeWeek->id] ?? null;
        $drivers = $report['drivers'] ?? collect();
        $driver = $drivers->firstWhere('id', $driverId);

        return $driver?->earnings ?? null;
    }

    protected function getSnapshotResults(TvdeWeek $tvdeWeek, int $driverId)
    {
        $current = CurrentAccount::where([
            'tvde_week_id' => $tvdeWeek->id,
            'driver_id'    => $driverId,
        ])->first();

        return $current ? json_decode($current->data) : null;
    }

    protected function normalizeResults($results): array
    {
        return [
            'total_net' => (float) data_get($results, 'total_net', 0),
            'car_track' => (float) data_get($results, 'car_track', 0),
            'fuel_transactions' => (float) data_get($results, 'fuel_transactions', 0),
            'vat_value' => (float) data_get($results, 'vat_value', 0),
            'total' => (float) data_get($results, 'total', 0),
            'adjustments_array' => data_get($results, 'adjustments_array', []),
        ];
    }

    protected function calculateDriverIva(Driver $driver, float $base): float
    {
        $rate = (float) ($driver->contract_vat->iva ?? 0);
        return round($base * ($rate / 100), 2);
    }

    protected function calculateDriverRf(Driver $driver, float $base): float
    {
        $rate = (float) ($driver->contract_vat->rf ?? 0);
        return round($base * ($rate / 100), 2);
    }

    protected function calculateFuelVat(float $fuelTotal): float
    {
        if ($fuelTotal <= 0) {
            return 0.0;
        }

        return ($fuelTotal / 1.23) * 0.23;
    }

    protected function sumCompanyAdjustments($adjustmentsArray): float
    {
        $adjustments = 0.0;

        if (empty($adjustmentsArray)) {
            return 0.0;
        }

        foreach ($adjustmentsArray as $adjustment) {
            $isCompanyExpense = (bool) data_get($adjustment, 'company_expense', false);
            if (!$isCompanyExpense) {
                continue;
            }

            $type = data_get($adjustment, 'type');
            $amount = (float) data_get($adjustment, 'amount', 0);

            $adjustments += ($type === 'deduct') ? -$amount : $amount;
        }

        return $adjustments;
    }

    protected function calculateVehicleExpenses(VehicleItem $vehicleItem, TvdeWeek $tvdeWeek): array
    {
        $expenses = VehicleExpense::where('vehicle_item_id', $vehicleItem->id)
            ->whereDate('date', '>=', $tvdeWeek->start_date)
            ->whereDate('date', '<=', $tvdeWeek->end_date)
            ->where(function ($query) {
                $query->whereNull('normalized_type')
                    ->orWhereIn('normalized_type', self::PROFITABLE_NORMALIZED_TYPES);
            })
            ->get();

        $treasury = 0.0;
        $vatTotal = 0.0;

        foreach ($expenses as $expense) {
            [$expenseTreasury, $expenseVat] = $this->calculateExpenseTreasuryAndVat($expense);
            $treasury += $expenseTreasury;

            if ($expense->normalized_type === 'acquisition') {
                continue;
            }

            $vatTotal += $expenseVat;
        }

        return [
            'treasury' => $treasury,
            'vat' => $vatTotal,
        ];
    }

    protected function calculateExpenseTreasuryAndVat(VehicleExpense $expense): array
    {
        $rate = (float) ($expense->vat ?? 0);
        $value = (float) ($expense->value ?? 0);
        $invoiceValue = $expense->invoice_value;

        // New logic: when invoice_value is provided, VAT is based on full invoice total
        // and treasury uses the paid amount (value).
        if ($invoiceValue !== null) {
            $invoiceTotal = (float) $invoiceValue;
            $vatAmount = $this->calculateVatFromGross($invoiceTotal, $rate);
            return [$value, $vatAmount];
        }

        // Legacy fallback: preserve previous behavior when invoice_value is absent.
        if ($rate > 0) {
            $vatAmount = $value * ($rate / 100);
            $gross = $value + $vatAmount;
            return [$gross, $vatAmount];
        }

        return [$value, 0.0];
    }

    protected function calculateVatFromGross(float $gross, float $rate): float
    {
        if ($gross <= 0 || $rate <= 0) {
            return 0.0;
        }

        $net = $gross / (1 + ($rate / 100));
        return $gross - $net;
    }

    protected function emptyRow(TvdeWeek $tvdeWeek): array
    {
        return [
            'week'  => $tvdeWeek,
            'year'  => Carbon::parse($tvdeWeek->start_date)->year,
            'month' => Carbon::parse($tvdeWeek->start_date)->month,
            'driver' => null,
            'results' => (object) [],
            'receipt' => null,
            'adjustments' => 0,
            'rf' => 0,
            'iva' => 0,
            'fuel_transactions_vat' => 0,
            'vehicle_expenses_value' => 0,
            'vehicle_expenses_vat' => 0,
            'expense_reimbursements_value' => 0,
            'total_treasury' => 0,
            'total_taxes' => 0,
            'final_total' => 0,
        ];
    }
}
