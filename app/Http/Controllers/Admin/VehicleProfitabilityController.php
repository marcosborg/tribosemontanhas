<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleItem;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\Traits\Reports;
use App\Models\TvdeWeek;
use App\Models\VehicleUsage;
use App\Models\VehicleExpense;
use App\Models\CurrentAccount;

class VehicleProfitabilityController extends Controller
{

    use Reports;

    public function index()
    {
        abort_if(Gate::denies('vehicle_profitability_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $filter = $this->filter();
        $company_id = $filter['company_id'];
        $tvde_week_id = $filter['tvde_week_id'];
        $tvde_years = $filter['tvde_years'];
        $tvde_year_id = $filter['tvde_year_id'];
        $tvde_months = $filter['tvde_months'];
        $tvde_month_id = $filter['tvde_month_id'];
        $tvde_weeks = $filter['tvde_weeks'];

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

        $tvde_week = TvdeWeek::find($tvde_week_id);

        //IDENTIFICAR O MOTORISTA

        $vehicle_usage = VehicleUsage::where('vehicle_item_id', $vehicle_item_id)
            ->whereDate('start_date', '<=', $tvde_week->start_date)
            ->whereDate('end_date', '<=', $tvde_week->end_date)
            ->with('driver')
            ->first();

        if (!$vehicle_usage) {
            $vehicle_usage = VehicleUsage::first()->load('driver');
            session()->put('vehicle_item_id', $vehicle_usage->vehicle_item_id);
        }

        $results = CurrentAccount::where([
            'tvde_week_id' => $tvde_week_id,
            'driver_id' => $vehicle_usage->driver->id
        ])->first();

        if ($results) {
            $results = json_decode($results->data);
        }

        // DESPESAS DA VIATURA
        $vehicle_expenses = VehicleExpense::where('vehicle_item_id', $vehicle_item->id)
            ->whereDate('date', '>=', $tvde_week->start_date)
            ->whereDate('date', '<=', $tvde_week->end_date)
            ->get();

        $vehicle_expenses_value = [];
        $vehicle_expenses_vat = [];

        if ($vehicle_expenses) {
            foreach ($vehicle_expenses as $vehicle_expense) {
                $vehicle_expenses_value[] = $vehicle_expense->value;
                if($vehicle_expense->vat > 0 || $vehicle_expense->vat !== NULL) {
                    $vehicle_expenses_vat[] = $vehicle_expense->value * ($vehicle_expense->vat / 100);
                }
            }
        }

        $vehicle_expenses_value = array_sum($vehicle_expenses_value);
        $vehicle_expenses_vat = array_sum($vehicle_expenses_vat);

        $vehicle_expenses = compact('vehicle_expenses_value', 'vehicle_expenses_vat');

        return view('admin.vehicleProfitabilities.index', compact([
            'tvde_year_id',
            'tvde_years',
            'tvde_months',
            'tvde_month_id',
            'tvde_weeks',
            'tvde_week_id',
            'vehicle_items',
            'vehicle_item_id',
            'results',
            'vehicle_expenses',
        ]));
    }

    public function setVehicleItemId($vehicle_item_id)
    {
        session()->put('vehicle_item_id', $vehicle_item_id);
        return redirect()->back();
    }
}
