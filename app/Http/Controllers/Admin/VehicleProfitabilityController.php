<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleItem;
use App\Models\TvdeWeek;
use App\Services\VehicleProfitabilityCalculator;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VehicleProfitabilityController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('vehicle_profitability_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // ---- Filtros vindos da query ----
        $period     = $request->input('period', 'week');     // week|month|year|custom
        $year       = $request->input('year');               // ex: 2025
        $month      = $request->input('month');              // 1..12
        $weeksIds   = (array) $request->input('weeks', []);  // array de IDs de semanas
        $startDate  = $request->input('start_date');         // YYYY-MM-DD
        $endDate    = $request->input('end_date');           // YYYY-MM-DD
        $groupBy    = $request->input('group_by', 'week');   // week|month|year

        // ---- Viatura selecionada (mantÇ¸m sessÇœo como jÇ­ fazias) ----
        $vehicle_items   = VehicleItem::with('driver')->get();
        $vehicle_item_id = session('vehicle_item_id', optional(VehicleItem::first())->id ?? 0);
        $vehicle_item    = VehicleItem::find($vehicle_item_id);

        // ---- Determinar semanas alvo conforme o perÇðodo ----
        $weeksQuery = TvdeWeek::query();

        switch ($period) {
            case 'year':
                if ($year) {
                    $weeksQuery->whereYear('start_date', $year);
                }
                break;

            case 'month':
                if ($year)  { $weeksQuery->whereYear('start_date', $year); }
                if ($month) { $weeksQuery->whereMonth('start_date', $month); }
                break;

            case 'custom':
                if ($startDate && $endDate) {
                    $weeksQuery->whereDate('start_date', '>=', $startDate)
                               ->whereDate('end_date',   '<=', $endDate);
                }
                break;

            case 'week':
            default:
                if (!empty($weeksIds)) {
                    $weeksQuery->whereIn('id', $weeksIds);
                } else {
                    // se nada vier selecionado, por defeito usa a semana mais recente
                    $last = TvdeWeek::orderBy('start_date', 'desc')->first();
                    if ($last) {
                        $weeksQuery->where('id', $last->id);
                    }
                }
                break;
        }

        $weeks = $weeksQuery->orderBy('start_date')->get();

        // Se nÇœo houver viatura ou semanas, devolve pÇ­gina ƒ?ovaziaƒ?? com filtros
        if (!$vehicle_item || $weeks->isEmpty()) {
            // Listas auxiliares para o formulÇ­rio (a partir das datas)
            $tvde_years  = TvdeWeek::selectRaw('YEAR(start_date) as y')->distinct()->orderBy('y', 'desc')->pluck('y');
            $tvde_months = TvdeWeek::when($year, fn($q) => $q->whereYear('start_date', $year))
                                   ->selectRaw('MONTH(start_date) as m')->distinct()->orderBy('m')->pluck('m');
            $tvde_weeks  = TvdeWeek::orderBy('start_date', 'desc')->get();

            return view('admin.vehicleProfitabilities.index', [
                'vehicle_items' => $vehicle_items,
                'vehicle_item_id' => $vehicle_item_id,
                'period' => $period,
                'groupBy' => $groupBy,
                'year' => $year,
                'month' => $month,
                'tvde_years' => $tvde_years,
                'tvde_months' => $tvde_months,
                'tvde_weeks' => $tvde_weeks,
                'weeks' => collect(),
                'rows' => [],
                'groups' => [],
                'totals' => ['treasury' => 0, 'taxes' => 0, 'final' => 0],
            ]);
        }

        // ---- CÇ­lculo por semana (reutilizÇ­vel) ----
        $calculator = app(VehicleProfitabilityCalculator::class);
        $rows = [];
        foreach ($weeks as $week) {
            $rows[] = $calculator->computeWeekMetrics($vehicle_item, $week);
        }

        // ---- Agrupar por week|month|year ----
        $groups = collect($rows)->groupBy(function ($row) use ($groupBy) {
            if ($groupBy === 'year')  return $row['year']; // 2025
            if ($groupBy === 'month') return sprintf('%04d-%02d', $row['year'], $row['month']); // 2025-06
            return $row['week']->id; // week
        })->map(function ($items) {
            return [
                'treasury' => collect($items)->sum('total_treasury'),
                'taxes'    => collect($items)->sum('total_taxes'),
                'final'    => collect($items)->sum('final_total'),
                'weeks'    => $items,
            ];
        });

        // ---- Totais globais do perÇðodo ----
        $totals = [
            'treasury' => $groups->sum('treasury'),
            'taxes'    => $groups->sum('taxes'),
            'final'    => $groups->sum('final'),
        ];

        // ---- Listas auxiliares para o formulÇ­rio (anos/meses/semanas) ----
        $tvde_years  = TvdeWeek::selectRaw('YEAR(start_date) as y')->distinct()->orderBy('y', 'desc')->pluck('y');
        $tvde_months = TvdeWeek::when($year, fn($q) => $q->whereYear('start_date', $year))
                               ->selectRaw('MONTH(start_date) as m')->distinct()->orderBy('m')->pluck('m');
        $tvde_weeks  = TvdeWeek::orderBy('start_date', 'desc')->get();

        return view('admin.vehicleProfitabilities.index', compact(
            'vehicle_items',
            'vehicle_item_id',
            'period',
            'groupBy',
            'year',
            'month',
            'tvde_years',
            'tvde_months',
            'tvde_weeks',
            'weeks',
            'rows',
            'groups',
            'totals'
        ));
    }

    public function setVehicleItemId($vehicle_item_id)
    {
        session()->put('vehicle_item_id', $vehicle_item_id);
        return redirect()->back();
    }
}
