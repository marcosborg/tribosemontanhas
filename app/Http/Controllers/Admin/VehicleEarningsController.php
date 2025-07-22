<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\Traits\Reports;
use App\Models\VehicleUsage;
use App\Models\VehicleItem;
use App\Models\TvdeWeek;
use App\Models\CurrentAccount;
use App\Models\Receipt;
use App\Models\VehicleExpense;
use App\Models\ExpenseReimbursement;

class VehicleEarningsController extends Controller
{

    use Reports;

    public function index()
    {
        abort_if(Gate::denies('vehicle_earning_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $filter = $this->filter();
        $company_id = $filter['company_id'];
        $tvde_week_id = $filter['tvde_week_id'];
        $tvde_years = $filter['tvde_years'];
        $tvde_year_id = $filter['tvde_year_id'];
        $tvde_months = $filter['tvde_months'];
        $tvde_month_id = $filter['tvde_month_id'];
        $tvde_weeks = $filter['tvde_weeks'];

        $tvde_week = TvdeWeek::find($tvde_week_id);

        $vehicles = VehicleItem::with(['driver', 'vehicle_brand', 'vehicle_model'])
            ->where('company_id', $company_id)
            ->get();

        $problematicVehicles = [];

        foreach ($vehicles as $vehicle) {
            $driver = $vehicle->driver;
            if (!$driver) continue;

            $vehicle_usage = VehicleUsage::where('vehicle_item_id', $vehicle->id)
                ->whereDate('start_date', '<=', $tvde_week->start_date)
                ->whereDate('end_date', '>=', $tvde_week->end_date)
                ->first();

            if (!$vehicle_usage) continue;

            $results = CurrentAccount::where([
                'tvde_week_id' => $tvde_week_id,
                'driver_id' => $driver->id,
            ])->first();

            $adjustments = 0;
            $rf = 0;
            $iva = 0;
            $fuel_transactions_vat = 0;

            if ($results) {
                $data = json_decode($results->data ?? '{}');

                $factor_iva = $driver->contract_vat->iva / 100;
                $factor_rf = $driver->contract_vat->rf / 100;

                $iva = number_format(($data->total ?? 0) * $factor_iva, 2);
                $rf = number_format(($data->total ?? 0) * $factor_rf, 2);
                $fuel_transactions_vat = ($data->fuel_transactions ?? 0) / 1.23 * 0.23;

                foreach ($data->adjustments_array ?? [] as $adjustment) {
                    if (isset($adjustment->company_expense) && $adjustment->company_expense) {
                        $amount = floatval($adjustment->amount);
                        $adjustments += ($adjustment->type === 'deduct') ? -$amount : $amount;
                    }
                }
            }

            $receipt = Receipt::where([
                'tvde_week_id' => $tvde_week_id,
                'driver_id' => $driver->id,
            ])->first();

            // Despesas da viatura
            $vehicle_expenses = VehicleExpense::where('vehicle_item_id', $vehicle->id)
                ->whereDate('date', '>=', $tvde_week->start_date)
                ->whereDate('date', '<=', $tvde_week->end_date)
                ->get();

            $vehicle_expenses_value = 0;
            $vehicle_expenses_vat = 0;

            foreach ($vehicle_expenses as $expense) {
                $vat = $expense->vat ?? 0;
                $value = $expense->value;
                if ($vat > 0) {
                    $vehicle_expenses_vat += $value * ($vat / 100);
                    $vehicle_expenses_value += $value * (1 + $vat / 100);
                } else {
                    $vehicle_expenses_value += $value;
                }
            }

            $expense_reimbursements = ExpenseReimbursement::where('vehicle_item_id', $vehicle->id)
                ->whereDate('date', '>=', $tvde_week->start_date)
                ->whereDate('date', '<=', $tvde_week->end_date)
                ->sum('value');

            $total_treasury =
                ($data->total_net ?? 0)
                - ($data->car_track ?? 0)
                - ($data->fuel_transactions ?? 0)
                + $adjustments
                - $rf
                - ($receipt->amount_transferred ?? 0)
                - $vehicle_expenses_value
                + $expense_reimbursements;

            $total_taxes =
                - ($data->vat_value ?? 0)
                    + $iva
                    + $fuel_transactions_vat
                    + $vehicle_expenses_vat;

            $final_total = $total_treasury + $total_taxes;

            // Se lucro <= 0 ou sem faturação
            if (empty($data->total_net) || $final_total <= 0) {
                $problematicVehicles[] = [
                    'vehicle' => $vehicle,
                    'driver' => $driver,
                    'final_total' => $final_total,
                ];
            }
        }

        return view('admin.vehicleEarnings.index')->with([
            'company_id' => $company_id,
            'tvde_year_id' => $tvde_year_id,
            'tvde_years' => $tvde_years,
            'tvde_months' => $tvde_months,
            'tvde_month_id' => $tvde_month_id,
            'tvde_weeks' => $tvde_weeks,
            'tvde_week_id' => $tvde_week_id,
            'problematicVehicles' => $problematicVehicles,
        ]);
    }
}
