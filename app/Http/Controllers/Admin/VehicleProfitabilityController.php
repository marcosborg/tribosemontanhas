<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleItem;
use App\Models\TvdeWeek;
use App\Services\VehicleProfitabilityCalculator;
use Carbon\Carbon;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VehicleProfitabilityController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('vehicle_profitability_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // Filters from query
        $period     = $request->input('period', 'week');     // week|month|year|custom
        $year       = $request->input('year');               // ex: 2025
        $month      = $request->input('month');              // 1..12
        $weeksIds   = (array) $request->input('weeks', []);  // array of week IDs
        $startDate  = $request->input('start_date');         // YYYY-MM-DD
        $endDate    = $request->input('end_date');           // YYYY-MM-DD
        $groupBy    = $request->input('group_by', 'week');   // week|month|year

        // Selected vehicle (keep session behavior)
        $vehicle_items = VehicleItem::with('driver')
            ->orderByRaw('UPPER(license_plate) ASC')
            ->get();
        $defaultVehicleItemId = optional($vehicle_items->first())->id ?? 0;
        $vehicle_item_id = session('vehicle_item_id', $defaultVehicleItemId);
        $isGlobal = (string) $vehicle_item_id === 'global';
        $vehicle_item = $isGlobal ? null : $vehicle_items->firstWhere('id', (int) $vehicle_item_id);

        // Resolve weeks for the period
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
                    // default to most recent week
                    $last = TvdeWeek::orderBy('start_date', 'desc')->first();
                    if ($last) {
                        $weeksQuery->where('id', $last->id);
                    }
                }
                break;
        }

        $weeks = $weeksQuery->orderBy('start_date')->get();

        // If no vehicle or weeks, return empty view with filters
        if (($isGlobal && $weeks->isEmpty()) || (!$isGlobal && !$vehicle_item) || $weeks->isEmpty()) {
            $tvde_years  = TvdeWeek::selectRaw('YEAR(start_date) as y')->distinct()->orderBy('y', 'desc')->pluck('y');
            $tvde_months = TvdeWeek::when($year, fn($q) => $q->whereYear('start_date', $year))
                                   ->selectRaw('MONTH(start_date) as m')->distinct()->orderBy('m')->pluck('m');
            $tvde_weeks  = TvdeWeek::orderBy('start_date', 'desc')->get();

            return view('admin.vehicleProfitabilities.index', [
                'vehicle_items' => $vehicle_items,
                'vehicle_item_id' => $vehicle_item_id,
                'isGlobal' => $isGlobal,
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
                'chart' => ['labels' => [], 'treasury' => [], 'taxes' => [], 'final' => []],
                'globalRows' => [],
                'globalChart' => ['labels' => [], 'final' => []],
            ]);
        }

        // Compute metrics per week using live calculator
        $calculator = app(VehicleProfitabilityCalculator::class);

        // Carregar a lista de despesas por semana pode ficar pesado em períodos longos.
        // Mantemos "ligado" por defeito quando o utilizador está a analisar poucas semanas.
        $includeExpenseItems = $weeks->count() <= 12;
        $rows = [];
        $groups = collect();
        $totals = ['treasury' => 0, 'taxes' => 0, 'final' => 0];
        $chart = ['labels' => [], 'treasury' => [], 'taxes' => [], 'final' => []];
        $globalRows = [];
        $globalChart = ['labels' => [], 'final' => []];

        if ($isGlobal) {
            foreach ($vehicle_items as $vehicle) {
                $vehicleRows = [];

                foreach ($weeks as $week) {
                    $vehicleRows[] = $calculator->computeWeekMetrics($vehicle, $week, false);
                }

                $vehicleTotals = [
                    'vehicle_item' => $vehicle,
                    'treasury' => collect($vehicleRows)->sum('total_treasury'),
                    'taxes' => collect($vehicleRows)->sum('total_taxes'),
                    'final' => collect($vehicleRows)->sum('final_total'),
                    'rows' => $vehicleRows,
                ];

                $globalRows[] = $vehicleTotals;
            }

            $globalRows = collect($globalRows)
                ->sortBy(fn ($row) => mb_strtoupper((string) $row['vehicle_item']->license_plate))
                ->values();

            $totals = [
                'treasury' => $globalRows->sum('treasury'),
                'taxes' => $globalRows->sum('taxes'),
                'final' => $globalRows->sum('final'),
            ];

            $globalChart = [
                'labels' => $globalRows->map(fn ($row) => $row['vehicle_item']->license_plate)->all(),
                'final' => $globalRows->map(fn ($row) => round((float) $row['final'], 2))->all(),
            ];
        } else {
            foreach ($weeks as $week) {
                $rows[] = $calculator->computeWeekMetrics($vehicle_item, $week, $includeExpenseItems);
            }

            // Group by week|month|year
            $groups = collect($rows)->groupBy(function ($row) use ($groupBy) {
                if ($groupBy === 'year')  return $row['year'];
                if ($groupBy === 'month') return sprintf('%04d-%02d', $row['year'], $row['month']);
                return $row['week']->id;
            })->map(function ($items) {
                return [
                    'treasury' => collect($items)->sum('total_treasury'),
                    'taxes'    => collect($items)->sum('total_taxes'),
                    'final'    => collect($items)->sum('final_total'),
                    'weeks'    => $items,
                ];
            });

            // Totals for the period
            $totals = [
                'treasury' => $groups->sum('treasury'),
                'taxes'    => $groups->sum('taxes'),
                'final'    => $groups->sum('final'),
            ];

            $chart = $this->buildChartSeries($groups, $weeks, $groupBy);
        }

        // Aux lists for the filters
        $tvde_years  = TvdeWeek::selectRaw('YEAR(start_date) as y')->distinct()->orderBy('y', 'desc')->pluck('y');
        $tvde_months = TvdeWeek::when($year, fn($q) => $q->whereYear('start_date', $year))
                               ->selectRaw('MONTH(start_date) as m')->distinct()->orderBy('m')->pluck('m');
        $tvde_weeks  = TvdeWeek::orderBy('start_date', 'desc')->get();

        return view('admin.vehicleProfitabilities.index', compact(
            'vehicle_items',
            'vehicle_item_id',
            'isGlobal',
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
            'totals',
            'chart',
            'globalRows',
            'globalChart'
        ));
    }

    private function buildChartSeries($groups, $weeks, string $groupBy): array
    {
        $labels = [];

        if ($weeks->isEmpty()) {
            return ['labels' => [], 'treasury' => [], 'taxes' => [], 'final' => []];
        }

        if ($groupBy === 'month') {
            $start = Carbon::parse($weeks->min('start_date'))->startOfMonth();
            $end = Carbon::parse($weeks->max('end_date'))->startOfMonth();
            $cursor = $start->copy();
            while ($cursor <= $end) {
                $labels[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }
        } elseif ($groupBy === 'year') {
            $startYear = Carbon::parse($weeks->min('start_date'))->year;
            $endYear = Carbon::parse($weeks->max('end_date'))->year;
            for ($year = $startYear; $year <= $endYear; $year++) {
                $labels[] = (string) $year;
            }
        } else {
            $labels = $weeks->pluck('id')->map(fn($id) => (string) $id)->all();
        }

        $chart = [
            'labels' => [],
            'treasury' => [],
            'taxes' => [],
            'final' => [],
        ];

        $groupMap = $groups->toArray();

        foreach ($labels as $label) {
            $entry = $groupMap[$label] ?? null;
            $chart['labels'][] = $label;
            $chart['treasury'][] = $entry['treasury'] ?? 0;
            $chart['taxes'][] = $entry['taxes'] ?? 0;
            $chart['final'][] = $entry['final'] ?? 0;
        }

        return $chart;
    }

    public function setVehicleItemId($vehicle_item_id)
    {
        session()->put('vehicle_item_id', $vehicle_item_id);
        return redirect()->back();
    }
}
