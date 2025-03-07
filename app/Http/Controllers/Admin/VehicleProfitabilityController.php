<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractVat;
use App\Models\CurrentAccount;
use App\Models\Driver;
use App\Models\Receipt;
use App\Models\TvdeWeek;
use App\Models\VehicleExpense;
use App\Models\VehicleItem;
use App\Models\VehicleUsage;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class VehicleProfitabilityController extends Controller
{
    public function index($start_date = null, $end_date = null)
    {
        abort_if(Gate::denies('vehicle_profitability_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicle_items = VehicleItem::all()->load('driver');

        if (!session()->has('vehicle_item_id')) {
            $vehicle_item = VehicleItem::first();
            if ($vehicle_item) {
                $vehicle_item_id = $vehicle_item->id;
            } else {
                $vehicle_item_id = 0;
            }
            session()->put('vehicle_item_id', $vehicle_item_id);
        } else {
            $vehicle_item_id = session()->get('vehicle_item_id');
            $vehicle_item = VehicleItem::find($vehicle_item_id);
        }

        if (!$start_date || !$end_date) {

            $tvde_week_id = session()->get('tvde_week_id');
            $tvde_week = TvdeWeek::find($tvde_week_id);

            if ($tvde_week) {
                $start_date = Carbon::parse($tvde_week->start_date);
                $year = $start_date->year;
                $month = $start_date->month;
            }
        }

        // TIPO DE VISTA

        if (!$start_date || !$end_date) {
            $screen = 'weeks';
        } else {
            $start_date = Carbon::parse($start_date);
            $end_date = Carbon::parse($end_date);
            if ($start_date->year !== $end_date->year) {
                $screen = 'years';
            } elseif ($start_date->year === $end_date->year && $start_date->month !== $end_date->month) {
                $screen = 'months';
            } elseif ($start_date->month === $end_date->month && $start_date->year === $end_date->year) {
                $screen = 'weeks';
            }
        }

        $driver_id = $vehicle_item->driver ? $vehicle_item->driver->id : 0;

        // Definir o período da tvde_week
        $tvde_start = $tvde_week->start_date;
        $tvde_end = $tvde_week->end_date;

        // Buscar o VehicleUsage correspondente ao período da tvde_week e ao vehicle_item_id atual
        $vehicleUsage = VehicleUsage::where('vehicle_item_id', $vehicle_item_id)
            ->where(function ($query) use ($tvde_start, $tvde_end) {
                $query->whereBetween('start_date', [$tvde_start, $tvde_end])
                    ->orWhereBetween('end_date', [$tvde_start, $tvde_end])
                    ->orWhere(function ($query) use ($tvde_start, $tvde_end) {
                        $query->where('start_date', '<=', $tvde_start)
                            ->where('end_date', '>=', $tvde_end);
                    });
            })
            ->latest('start_date') // Pegamos o mais recente no período
            ->first();

        if ($vehicleUsage) {
            $driver_id = $vehicleUsage->driver_id; // Atualiza driver_id com base no VehicleUsage
        }

        if ($screen == 'weeks') {
            $start_date = Carbon::parse($tvde_week->start_date);
            $year = $start_date->year;
            $month = $start_date->month;

            $current_accounts = CurrentAccount::whereHas('tvde_week', function ($query) use ($year, $month) {
                $query->whereYear('start_date', $year)
                    ->whereMonth('start_date', $month);
            })
                ->where('driver_id', $driver_id)
                ->get()->load('tvde_week');

            $driver = Driver::find($driver_id);

            if($driver) {
                $contract_vat = $driver->load('contract_vat')->contract_vat;
            } else {
                $contract_vat = 0;
            }

            $dates = $current_accounts->map(function ($account) {
                return [
                    'start_date' => $account->tvde_week->start_date,
                    'end_date' => $account->tvde_week->end_date,
                ];
            });

            $datas = [];

            foreach ($current_accounts as $key => $current_account) {

                $encoded_data = $current_account->data;
                $data = json_decode($encoded_data);
                $vehicle_expenses_value = 0;
                $vehicle_expenses_iva = 0;
                $vehicle_expenses = VehicleExpense::whereBetween('date', [$current_account->tvde_week->start_date, $current_account->tvde_week->end_date])->get();
                foreach ($vehicle_expenses as $vehicle_expense) {
                    $vehicle_expenses_value = $vehicle_expenses_value + $vehicle_expense->value;
                    if ($vehicle_expense->vat > 0) {
                        $vehicle_expense_vat_factor = ($vehicle_expense->vat / 100);
                        $vehicle_expenses_iva = $vehicle_expenses_iva + ($vehicle_expenses_value * $vehicle_expense_vat_factor);
                    }
                }
                //CHECK RECEIPT
                $receipt = Receipt::where('tvde_week_id', $current_account->tvde_week_id)->first();
                if ($receipt) {
                    $factor = $contract_vat->iva / 100;
                    $data->iva['gross_iva'] = number_format(($data->total * $factor), 2, '.') ?? 0;
                    $factor = $contract_vat->rf / 100;
                    $rf = number_format(($receipt->verified_value * $factor), 2, '.');
                    $data->rf = $rf ?? 0;
                    $data->salary = $data->total - $rf + $data->iva['gross_iva'];
                    $data->iva['vat_value'] = $data->vat_value;
                    $data->iva['fuel_transactions_iva'] = ($data->fuel_transactions / 1.23) * 0.23;
                    $data->tvde_week = $current_account->tvde_week;
                    $data->vehicle_expenses = $vehicle_expenses_value > 0 ? $vehicle_expenses_value * 1.23 : 0;
                    $data->total_expense = $data->total_net ?? 0 - $data->fuel_transactions - $data->car_track - $rf - (isset($data->company_expense) ? $data->company_expense : $data->adjustments) - $receipt->amount_transferred - $data->vehicle_expenses;
                    $data->vat = $data->iva['fuel_transactions_iva'] + $data->iva['gross_iva'] - $data->vat_value - $vehicle_expenses_iva;
                    $data->total_exercise = $data->total_expense + $data->vat;
                    $data->receipt = $receipt;
                } else {
                    $factor = $contract_vat->iva / 100;
                    $data->iva['gross_iva'] = number_format(($data->total * $factor), 2, '.') ?? 0;
                    $factor = $contract_vat->rf / 100;
                    $rf = number_format(($data->total * $factor), 2, '.');
                    $data->rf = $rf ?? 0;
                    $data->salary = $data->total - $rf + $data->iva['gross_iva'];
                    $data->iva['vat_value'] = $data->vat_value;
                    $data->iva['fuel_transactions_iva'] = ($data->fuel_transactions / 1.23) * 0.23;
                    $data->tvde_week = $current_account->tvde_week;
                    $data->vehicle_expenses = $vehicle_expenses ?? 0;
                    $data->total_expense = $data->total_net ?? 0 - $data->fuel_transactions - $data->car_track - (isset($data->company_expense) ? $data->company_expense : $data->adjustments) - $vehicle_expenses_value;
                    $data->vat = $data->iva['fuel_transactions_iva'] - $data->vat_value - $vehicle_expenses_iva;
                    $data->total_exercise = $data->total_expense + $data->vat;
                    $data->receipt = $receipt;
                }
                $datas[] = $data;
            }
        }

        return view('admin.vehicleProfitabilities.index', compact([
            'vehicle_items',
            'vehicle_item_id',
            'datas'
        ]));
    }

    public function setVehicleItemId($vehicle_item_id)
    {
        session()->put('vehicle_item_id', $vehicle_item_id);
        return redirect()->back();
    }

    public function setInterval(Request $request)
    {
        return redirect('/admin/vehicle-profitabilities/' . $request->start_date . '/' . $request->end_date);
    }
}
