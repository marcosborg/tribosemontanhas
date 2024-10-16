<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TvdeWeek;
use App\Models\VehicleItem;
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

        if ($screen == 'weeks') {
            $start_date = Carbon::parse($tvde_week->start_date);
            $year = $start_date->year;
            $month = $start_date->month;

            $exercicio_total = ''
        }

        return view('admin.vehicleProfitabilities.index', compact([
            'vehicle_items',
            'vehicle_item_id'
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
