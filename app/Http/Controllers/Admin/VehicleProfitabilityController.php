<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleItem;
use App\Models\TvdeWeek;
use App\Services\VehicleProfitabilityCalculator;
use Carbon\Carbon;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Selected vehicle list filtered by financial movement in the selected period
        $vehicle_items = $this->queryVehicleItemsWithFinancialMovement($weeks)
            ->with('driver')
            ->orderByRaw('UPPER(license_plate) ASC')
            ->get();
        $defaultVehicleItemId = optional($vehicle_items->first())->id ?? 0;
        $vehicle_item_id = session('vehicle_item_id', $defaultVehicleItemId);
        $isGlobal = (string) $vehicle_item_id === 'global';
        $vehicle_item = $isGlobal ? null : $vehicle_items->firstWhere('id', (int) $vehicle_item_id);

        if (!$isGlobal && !$vehicle_item && $defaultVehicleItemId) {
            $vehicle_item_id = $defaultVehicleItemId;
            $vehicle_item = $vehicle_items->firstWhere('id', (int) $vehicle_item_id);
        }

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

    private function queryVehicleItemsWithFinancialMovement($weeks)
    {
        $query = VehicleItem::query();

        if ($weeks->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $weekIds = $weeks->pluck('id')->map(fn ($id) => (int) $id)->all();
        $periodStart = Carbon::parse($weeks->map(fn ($week) => $week->getRawOriginal('start_date'))->min())->startOfDay();
        $periodEnd = Carbon::parse($weeks->map(fn ($week) => $week->getRawOriginal('end_date'))->max())->endOfDay();

        return $query->where(function ($vehicleQuery) use ($weekIds, $periodStart, $periodEnd) {
            $vehicleQuery
                // Direct vehicle expenses
                ->whereExists(function ($sub) use ($periodStart, $periodEnd) {
                    $sub->select(DB::raw(1))
                        ->from('vehicle_expenses as ve')
                        ->whereColumn('ve.vehicle_item_id', 'vehicle_items.id')
                        ->whereNull('ve.deleted_at')
                        ->whereBetween('ve.date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
                })
                // Direct reimbursements
                ->orWhereExists(function ($sub) use ($periodStart, $periodEnd) {
                    $sub->select(DB::raw(1))
                        ->from('expense_reimbursements as er')
                        ->whereColumn('er.vehicle_item_id', 'vehicle_items.id')
                        ->whereNull('er.deleted_at')
                        ->whereBetween('er.date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
                })
                // Revenue by driver/week usage
                ->orWhereExists(function ($sub) use ($weekIds) {
                    $sub->select(DB::raw(1))
                        ->from('vehicle_usages as vu')
                        ->join('tvde_weeks as tw', function ($join) use ($weekIds) {
                            $join->whereIn('tw.id', $weekIds)
                                ->whereNull('tw.deleted_at');
                        })
                        ->join('drivers as d', 'd.id', '=', 'vu.driver_id')
                        ->join('tvde_activities as ta', function ($join) {
                            $join->on('ta.tvde_week_id', '=', 'tw.id')
                                ->on('ta.company_id', '=', 'vehicle_items.company_id')
                                ->where(function ($or) {
                                    $or->whereColumn('ta.driver_code', 'd.uber_uuid')
                                        ->orWhereColumn('ta.driver_code', 'd.bolt_name');
                                });
                        })
                        ->whereColumn('vu.vehicle_item_id', 'vehicle_items.id')
                        ->whereNull('vu.deleted_at')
                        ->whereNull('d.deleted_at')
                        ->whereNull('ta.deleted_at')
                        ->where('vu.start_date', '<=', DB::raw("CONCAT(tw.end_date, ' 23:59:59')"))
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('vu.end_date')
                                ->orWhere('vu.end_date', '>=', DB::raw("CONCAT(tw.start_date, ' 00:00:00')"));
                        });
                })
                // Transfers / receipts by driver/week usage
                ->orWhereExists(function ($sub) use ($weekIds) {
                    $sub->select(DB::raw(1))
                        ->from('vehicle_usages as vu')
                        ->join('tvde_weeks as tw', function ($join) use ($weekIds) {
                            $join->whereIn('tw.id', $weekIds)
                                ->whereNull('tw.deleted_at');
                        })
                        ->join('receipts as r', 'r.driver_id', '=', 'vu.driver_id')
                        ->whereColumn('vu.vehicle_item_id', 'vehicle_items.id')
                        ->whereNull('vu.deleted_at')
                        ->whereNull('r.deleted_at')
                        ->whereColumn('r.tvde_week_id', 'tw.id')
                        ->where('vu.start_date', '<=', DB::raw("CONCAT(tw.end_date, ' 23:59:59')"))
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('vu.end_date')
                                ->orWhere('vu.end_date', '>=', DB::raw("CONCAT(tw.start_date, ' 00:00:00')"));
                        });
                })
                // Company expense adjustments that affect treasury
                ->orWhereExists(function ($sub) use ($weekIds) {
                    $sub->select(DB::raw(1))
                        ->from('vehicle_usages as vu')
                        ->join('tvde_weeks as tw', function ($join) use ($weekIds) {
                            $join->whereIn('tw.id', $weekIds)
                                ->whereNull('tw.deleted_at');
                        })
                        ->join('adjustment_driver as ad', 'ad.driver_id', '=', 'vu.driver_id')
                        ->join('adjustments as a', 'a.id', '=', 'ad.adjustment_id')
                        ->whereColumn('vu.vehicle_item_id', 'vehicle_items.id')
                        ->whereNull('vu.deleted_at')
                        ->whereNull('a.deleted_at')
                        ->whereColumn('a.company_id', 'vehicle_items.company_id')
                        ->where('a.company_expense', 1)
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('a.start_date')
                                ->orWhereColumn('a.start_date', '<=', 'tw.start_date');
                        })
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('a.end_date')
                                ->orWhereColumn('a.end_date', '>=', 'tw.end_date');
                        })
                        ->where('vu.start_date', '<=', DB::raw("CONCAT(tw.end_date, ' 23:59:59')"))
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('vu.end_date')
                                ->orWhere('vu.end_date', '>=', DB::raw("CONCAT(tw.start_date, ' 00:00:00')"));
                        });
                })
                // Fuel transactions via main or pivot cards in selected weeks
                ->orWhereExists(function ($sub) use ($weekIds) {
                    $sub->select(DB::raw(1))
                        ->from('vehicle_usages as vu')
                        ->join('tvde_weeks as tw', function ($join) use ($weekIds) {
                            $join->whereIn('tw.id', $weekIds)
                                ->whereNull('tw.deleted_at');
                        })
                        ->join('drivers as d', 'd.id', '=', 'vu.driver_id')
                        ->leftJoin('cards as c_main', 'c_main.id', '=', 'd.card_id')
                        ->leftJoin('card_driver as cd', 'cd.driver_id', '=', 'd.id')
                        ->leftJoin('cards as c_pivot', 'c_pivot.id', '=', 'cd.card_id')
                        ->join('combustion_transactions as ct', function ($join) {
                            $join->where(function ($cardQuery) {
                                $cardQuery->whereColumn('ct.card', 'c_main.code')
                                    ->orWhereColumn('ct.card', 'c_pivot.code');
                            });
                        })
                        ->whereColumn('vu.vehicle_item_id', 'vehicle_items.id')
                        ->whereNull('vu.deleted_at')
                        ->whereNull('d.deleted_at')
                        ->whereNull('ct.deleted_at')
                        ->whereColumn('ct.tvde_week_id', 'tw.id')
                        ->where('vu.start_date', '<=', DB::raw("CONCAT(tw.end_date, ' 23:59:59')"))
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('vu.end_date')
                                ->orWhere('vu.end_date', '>=', DB::raw("CONCAT(tw.start_date, ' 00:00:00')"));
                        });
                })
                // Electric transactions in selected weeks
                ->orWhereExists(function ($sub) use ($weekIds) {
                    $sub->select(DB::raw(1))
                        ->from('vehicle_usages as vu')
                        ->join('tvde_weeks as tw', function ($join) use ($weekIds) {
                            $join->whereIn('tw.id', $weekIds)
                                ->whereNull('tw.deleted_at');
                        })
                        ->join('drivers as d', 'd.id', '=', 'vu.driver_id')
                        ->join('electrics as e', 'e.id', '=', 'd.electric_id')
                        ->join('electric_transactions as et', 'et.card', '=', 'e.code')
                        ->whereColumn('vu.vehicle_item_id', 'vehicle_items.id')
                        ->whereNull('vu.deleted_at')
                        ->whereNull('d.deleted_at')
                        ->whereNull('et.deleted_at')
                        ->whereColumn('et.tvde_week_id', 'tw.id')
                        ->where('vu.start_date', '<=', DB::raw("CONCAT(tw.end_date, ' 23:59:59')"))
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('vu.end_date')
                                ->orWhere('vu.end_date', '>=', DB::raw("CONCAT(tw.start_date, ' 00:00:00')"));
                        });
                })
                // Toll / car track by real date assigned to the vehicle on that date
                ->orWhereExists(function ($sub) use ($periodStart, $periodEnd) {
                    $sub->select(DB::raw(1))
                        ->from('car_tracks as ct')
                        ->join('vehicle_usages as vu', function ($join) {
                            $join->on('vu.vehicle_item_id', '=', 'vehicle_items.id')
                                ->whereNull('vu.deleted_at')
                                ->whereRaw("REPLACE(REPLACE(UPPER(vehicle_items.license_plate), ' ', ''), '-', '') = REPLACE(REPLACE(UPPER(ct.license_plate), ' ', ''), '-', '')")
                                ->whereRaw('vu.start_date <= ct.date')
                                ->whereRaw('(vu.end_date IS NULL OR vu.end_date >= ct.date)');
                        })
                        ->whereNull('ct.deleted_at')
                        ->whereBetween('ct.date', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()]);
                })
                // Tesla charging by real date and license match
                ->orWhereExists(function ($sub) use ($periodStart, $periodEnd) {
                    $sub->select(DB::raw(1))
                        ->from('tesla_chargings as tc')
                        ->join('vehicle_usages as vu', function ($join) {
                            $join->on('vu.vehicle_item_id', '=', 'vehicle_items.id')
                                ->whereNull('vu.deleted_at')
                                ->whereRaw("REPLACE(REPLACE(UPPER(vehicle_items.license_plate), ' ', ''), '-', '') = REPLACE(REPLACE(UPPER(tc.license), ' ', ''), '-', '')")
                                ->whereRaw('vu.start_date <= tc.datetime')
                                ->whereRaw('(vu.end_date IS NULL OR vu.end_date >= tc.datetime)');
                        })
                        ->whereNull('tc.deleted_at')
                        ->whereBetween('tc.datetime', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()]);
                });
        });
    }
}
