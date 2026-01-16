<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyVehicleUsageRequest;
use App\Http\Requests\StoreVehicleUsageRequest;
use App\Http\Requests\UpdateVehicleUsageRequest;
use App\Models\Driver;
use App\Models\VehicleItem;
use App\Models\VehicleUsage;
use App\Models\CarHire;
use App\Models\TvdeActivity;
use App\Models\TvdeWeek;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class VehicleUsageController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('vehicle_usage_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {

            $query = VehicleUsage::with(['driver', 'vehicle_item'])
                ->select(sprintf('%s.*', (new VehicleUsage)->table));

            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'vehicle_usage_show';
                $editGate = 'vehicle_usage_edit';
                $deleteGate = 'vehicle_usage_delete';
                $crudRoutePart = 'vehicle-usages';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', fn($row) => $row->id ?: '');

            // Relacionais (alias)
            $table->addColumn('driver_name', fn($row) => $row->driver?->name ?: '');
            $table->addColumn('vehicle_item_license_plate', fn($row) => $row->vehicle_item?->license_plate ?: '');

            // Mostrar usage_exceptions: aceita string (key) ou JSON com vÃ¡rias keys
            $map = VehicleUsage::USAGE_EXCEPTIONS_RADIO ?? [];
            $table->editColumn('usage_exceptions', function ($row) use ($map) {
                $val = $row->usage_exceptions;

                if ($val === null || $val === '') {
                    return '';
                }

                // Se for JSON vÃ¡lido, mapeia cada item
                try {
                    $decoded = is_string($val) ? json_decode($val, true) : null;
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $labels = [];
                        foreach ($decoded as $k) {
                            $labels[] = $map[$k] ?? (is_string($k) ? $k : json_encode($k));
                        }
                        return implode(', ', array_filter($labels));
                    }
                } catch (\Throwable $e) {
                    // segue para fallback textual
                }

                // Se for string simples (key)
                return $map[$val] ?? $val;
            });

            // ===== Filtros server-side por coluna =====

            // Driver (nome)
            $table->filterColumn('driver_name', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->whereHas('driver', fn($qq) => $qq->where('name', 'like', "%{$k}%"));
            });

            // MatrÃ­cula
            $table->filterColumn('vehicle_item_license_plate', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->whereHas('vehicle_item', fn($qq) => $qq->where('license_plate', 'like', "%{$k}%"));
            });

            // Datas (LIKE simples; muda para BETWEEN se precisares)
            $table->filterColumn('start_date', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->where('start_date', 'like', "%{$k}%");
            });
            $table->filterColumn('end_date', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->where('end_date', 'like', "%{$k}%");
            });

            // usage_exceptions: pesquisa por chave, rÃ³tulo ou conteÃºdo JSON
            $table->filterColumn('usage_exceptions', function ($q, $k) use ($map) {
                $k = trim($k);
                if ($k === '')
                    return;

                // encontra keys cujos labels OU keys combinem com o termo
                $keys = [];
                foreach ($map as $key => $label) {
                    if (stripos($label, $k) !== false || stripos($key, $k) !== false) {
                        $keys[] = $key;
                    }
                }

                $q->where(function ($qq) use ($k, $keys) {
                    // match direto por string
                    $qq->orWhere('usage_exceptions', 'like', "%{$k}%");

                    // match por keys conhecidas
                    if (count($keys)) {
                        $qq->orWhereIn('usage_exceptions', $keys);
                        // match dentro de JSON
                        foreach ($keys as $key) {
                            $qq->orWhereRaw('JSON_VALID(usage_exceptions) AND JSON_CONTAINS(COALESCE(usage_exceptions, "[]"), ?)', ['"' . $key . '"']);
                        }
                    } else {
                        // fallback para JSON textual (quando user escreve o label mas nÃ£o hÃ¡ no map)
                        $qq->orWhereRaw('JSON_VALID(usage_exceptions) AND JSON_SEARCH(usage_exceptions, "one", ?)', [$k]);
                    }
                });
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.vehicleUsages.index');
    }

    public function create()
    {
        abort_if(Gate::denies('vehicle_usage_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $drivers = Driver::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $vehicle_items = VehicleItem::pluck('license_plate', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.vehicleUsages.create', compact('drivers', 'vehicle_items'));
    }

    public function store(StoreVehicleUsageRequest $request)
    {
        // As datas estÇœo validadas no formato Y-m-d H:i:s, podemos usÇ­-las diretamente
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $endDateForOverlap = $endDate ?: '9999-12-31 23:59:59';

        // Criar SEMPRE o novo registo
        $newUsage = VehicleUsage::create($request->all());

        // Verificar sobreposiÇõÇœo com outros registos (excluindo o registo recÇ¸m-criado)
        $hasOverlap = VehicleUsage::where('vehicle_item_id', $request->vehicle_item_id)
            ->where('id', '!=', $newUsage->id)
            ->where(function ($query) use ($startDate, $endDateForOverlap) {
                $query->where('start_date', '<=', $endDateForOverlap)
                    ->where(function ($q) use ($startDate) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $startDate);
                    });
            })
            ->first();

        if ($hasOverlap) {
            return redirect()->route('admin.vehicle-usages.index')
                ->with('error_message', "UtilizaÇõÇœo criada com sucesso (ID {$newUsage->id}), mas sobrepÇæe a utilizaÇõÇœo existente com ID {$hasOverlap->id}.");
        }

        return redirect()->route('admin.vehicle-usages.index')
            ->with('success', "UtilizaÇõÇœo criada com sucesso (ID {$newUsage->id}).");
    }

    public function edit(VehicleUsage $vehicleUsage)
    {
        abort_if(Gate::denies('vehicle_usage_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $drivers = Driver::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $vehicle_items = VehicleItem::pluck('license_plate', 'id')->prepend(trans('global.pleaseSelect'), '');

        $vehicleUsage->load('driver', 'vehicle_item');

        return view('admin.vehicleUsages.edit', compact('drivers', 'vehicleUsage', 'vehicle_items'));
    }

    public function update(UpdateVehicleUsageRequest $request, VehicleUsage $vehicleUsage)
    {
        $vehicleUsage->update($request->all());

        return redirect()->route('admin.vehicle-usages.index');
    }

    public function show(VehicleUsage $vehicleUsage)
    {
        abort_if(Gate::denies('vehicle_usage_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicleUsage->load('driver', 'vehicle_item');

        return view('admin.vehicleUsages.show', compact('vehicleUsage'));
    }

    public function destroy(VehicleUsage $vehicleUsage)
    {
        abort_if(Gate::denies('vehicle_usage_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicleUsage->delete();

        return back();
    }

    public function massDestroy(MassDestroyVehicleUsageRequest $request)
    {
        $vehicleUsages = VehicleUsage::find(request('ids'));

        foreach ($vehicleUsages as $vehicleUsage) {
            $vehicleUsage->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function usage()
    {
        // --- Carrega usos, agrupado por matrÃ­cula ---
        $usages = VehicleUsage::with(['vehicle_item', 'driver'])
            ->orderBy('start_date')
            ->get();

        $grouped = $usages
            ->groupBy('vehicle_item.license_plate')
            ->sortKeysUsing(static fn($a, $b) => strcasecmp((string) $a, (string) $b)); // matrÃ­culas em ordem alfabÃ©tica
        $normalizedByPlate = [];
        $timelineItems = [];

        foreach ($grouped as $plate => $usagesForVehicle) {
            $sorted = $usagesForVehicle->sortBy(function ($item) {
                return (string) ($item->getRawOriginal('start_date') ?? '');
            })->values();
            $normalized = [];

            for ($i = 0; $i < $sorted->count(); $i++) {
                $current = $sorted[$i];
                $startRaw = $current->getRawOriginal('start_date');
                if (!$startRaw) {
                    continue;
                }

                try {
                    $start = Carbon::parse($startRaw);
                } catch (\Throwable $e) {
                    continue;
                }
                $endRaw = $current->getRawOriginal('end_date');
                if ($endRaw) {
                    try {
                        $end = Carbon::parse($endRaw);
                    } catch (\Throwable $e) {
                        $end = null;
                    }
                } else {
                    $end = null;
                }

                $next = $sorted[$i + 1] ?? null;
                if ($next) {
                    $nextStartRaw = $next->getRawOriginal('start_date');
                    if ($nextStartRaw) {
                        try {
                            $nextStart = Carbon::parse($nextStartRaw);
                            if ($end === null || $end->greaterThanOrEqualTo($nextStart)) {
                                $end = $nextStart->copy()->subSecond();
                            }
                        } catch (\Throwable $e) {
                            // ignore invalid next start date
                        }
                    }
                }

                if ($end && $end->lessThan($start)) {
                    $end = $start->copy();
                }

                $normalized[] = (object) [
                    'id' => $current->id,
                    'driver' => $current->driver,
                    'usage_exceptions' => $current->usage_exceptions,
                    'start' => $start,
                    'end' => $end,
                ];
            }

            $normalizedByPlate[$plate] = $normalized;
        }

        foreach ($normalizedByPlate as $plate => $usagesForVehicle) {
            foreach ($usagesForVehicle as $usage) {
                $content = $usage->driver
                    ? $usage->driver->name
                    : ($usage->usage_exceptions ? ucfirst($usage->usage_exceptions) : 'Sem motorista');

                $className = null;
                if ($usage->usage_exceptions) {
                    $className = $usage->usage_exceptions . '-item';
                } elseif (!$usage->driver) {
                    $className = 'exception-item';
                }

                $timelineItems[] = [
                    'id' => $usage->id,
                    'content' => $content,
                    'start' => $usage->start->format('Y-m-d H:i:s'),
                    'end' => $usage->end ? $usage->end->format('Y-m-d H:i:s') : null,
                    'group' => $plate,
                    'className' => $className,
                ];
            }
        }

        $occupancyStats = [];
        $monthlyStats = [];
        $monthlyStackedStats = []; // para o grÃ¡fico
        $yearlyMap = [];

        // 1) Ãšltima semana com dados em tvde_activity -> tvde_week_id
        $lastActivity = TvdeActivity::query()
            ->orderByDesc('id')
            ->first();
        $lastWeekId = optional($lastActivity)->tvde_week_id;

        // 2) Intervalo da semana (fallback = semana corrente)
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        if ($lastWeekId) {
            $tw = TvdeWeek::query()->find($lastWeekId);
            if ($tw && !empty($tw->start_date) && !empty($tw->end_date)) {
                try {
                    $weekStart = Carbon::parse($tw->start_date)->startOfDay();
                    $weekEnd = Carbon::parse($tw->end_date)->endOfDay();
                } catch (\Throwable $e) { /* mantÃ©m fallback */
                }
            }
        }

        // 3) Mapa de rendas por matrÃ­cula (rentByPlate) via CurrentAccount.data.car_hire
        //    PrÃ©-carrega todos os CurrentAccount da Ãºltima semana e indexa por driver_id
        $currentAccountsByDriver = collect();
        if ($lastWeekId) {
            $currentAccountsByDriver = \App\Models\CurrentAccount::query()
                ->where('tvde_week_id', $lastWeekId)
                ->get(['driver_id', 'data'])
                ->keyBy('driver_id');
        }

        $rentByPlate = [];

        foreach ($grouped as $plate => $usagesForVehicle) {
            /** @var VehicleItem|null $vehicleItem */
            $vehicleItem = optional($usagesForVehicle->first())->vehicle_item;

            if (!$vehicleItem) {
                $rentByPlate[$plate] = 300; // fallback visual
                continue;
            }

            // Driver = usage que INTERSECTA a semana
            $usageInWeek = VehicleUsage::query()
                ->where('vehicle_item_id', $vehicleItem->id)
                ->activeBetween($weekStart, $weekEnd)
                ->orderByRaw('CASE WHEN end_date IS NULL THEN 1 ELSE 0 END DESC')
                ->orderByDesc('end_date')
                ->first();

            $driverId = optional($usageInWeek)->driver_id;

            // Fallback: Ãºltimo usage com driver (se a semana nÃ£o tiver intersecÃ§Ã£o)
            if (!$driverId) {
                $lastWithDriver = VehicleUsage::query()
                    ->where('vehicle_item_id', $vehicleItem->id)
                    ->whereNotNull('driver_id')
                    ->orderByRaw('CASE WHEN end_date IS NULL THEN 1 ELSE 0 END DESC')
                    ->orderByDesc('end_date')
                    ->first();
                $driverId = optional($lastWithDriver)->driver_id;
            }

            // Tentar obter do CurrentAccount dessa semana: data->car_hire
            $rent = null;
            if ($driverId && isset($currentAccountsByDriver[$driverId])) {
                $payload = $currentAccountsByDriver[$driverId]->data;

                // Caso o campo nÃ£o esteja com cast para array, decodifica
                if (is_string($payload)) {
                    $decoded = json_decode($payload, true);
                    $payload = is_array($decoded) ? $decoded : [];
                } elseif (!is_array($payload)) {
                    $payload = [];
                }

                // caminho principal: car_hire na raiz do JSON (segundo o teu exemplo)
                // (mantÃ©m fallback para earnings.car_hire se algum mais antigo tiver assim)
                $rent = data_get($payload, 'car_hire');
                if ($rent === null) {
                    $rent = data_get($payload, 'earnings.car_hire');
                }
            }

            // fallback final (visual) caso nÃ£o haja CurrentAccount ou nÃ£o tenha car_hire
            $rentByPlate[$plate] = is_numeric($rent) ? (float) $rent : 300;
        }

        // 4) EstatÃ­sticas (igual ao teu original), acrescentando 'rent' a cada monthKey
        foreach ($normalizedByPlate as $plate => $usagesForVehicle) {
            $years = [];

            foreach ($usagesForVehicle as $usage) {
                $start = $usage->start;
                $end = $usage->end ?: Carbon::now()->endOfDay();
                $exception = $usage->usage_exceptions ?? 'usage';
                $period = CarbonPeriod::create($start, $end);

                foreach ($period as $day) {
                    $year = $day->year;
                    $month = $day->month;
                    $monthKey = sprintf("%s (%04d-%02d)", $plate, $year, $month);

                    if (!isset($monthlyStats[$monthKey])) {
                        $monthlyStats[$monthKey] = [
                            'label' => $monthKey,
                            'plate' => $plate,
                            'year' => (string) $year,
                            'month' => str_pad($month, 2, '0', STR_PAD_LEFT),
                            'days' => 0,
                        ];
                    }
                    $monthlyStats[$monthKey]['days']++;

                    if (!isset($monthlyStackedStats[$monthKey])) {
                        $monthlyStackedStats[$monthKey] = [
                            'label' => $monthKey,
                            'plate' => $plate,
                            'year' => (string) $year,
                            'month' => str_pad($month, 2, '0', STR_PAD_LEFT),
                            'usage' => 0,
                            'maintenance' => 0,
                            'accident' => 0,
                            'unassigned' => 0,
                            'personal' => 0,
                            'rent' => $rentByPlate[$plate] ?? null, // valor do CurrentAccount
                        ];
                    }
                    if (array_key_exists($exception, $monthlyStackedStats[$monthKey])) {
                        $monthlyStackedStats[$monthKey][$exception]++;
                    }

                    if (!isset($years[$year])) {
                        $years[$year] = [];
                    }
                    $years[$year][$day->format('Y-m-d')] = true;
                }
            }

            foreach ($years as $year => $usedDays) {
                $totalDays = Carbon::create($year, 1, 1)->daysInYear;
                $usedCount = count($usedDays);
                $occupancyStats[$plate][$year] = [
                    'used' => $usedCount,
                    'total' => $totalDays,
                    'percent' => round(($usedCount / $totalDays) * 100, 2),
                ];

                $yearKey = "$plate ($year)";
                if (!isset($yearlyMap[$yearKey])) {
                    $yearlyMap[$yearKey] = [
                        'label' => $yearKey,
                        'year' => (string) $year,
                        'totalPercent' => 0,
                        'months' => 0,
                    ];
                }
            }
        }

        foreach ($monthlyStats as &$stat) {
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) $stat['month'], (int) $stat['year']);
            $stat['percent'] = $daysInMonth > 0 ? round(($stat['days'] / $daysInMonth) * 100, 2) : 0;

            $yearKey = "{$stat['plate']} ({$stat['year']})";
            if (isset($yearlyMap[$yearKey])) {
                $yearlyMap[$yearKey]['totalPercent'] += $stat['percent'];
                $yearlyMap[$yearKey]['months']++;
            }
        }
        unset($stat);

        $yearlyStats = [];
        foreach ($yearlyMap as $entry) {
            $yearlyStats[] = [
                'label' => $entry['label'],
                'year' => $entry['year'],
                'percent' => $entry['months'] > 0 ? round($entry['totalPercent'] / $entry['months'], 2) : 0,
            ];
        }
        usort($yearlyStats, fn($a, $b) => $b['percent'] <=> $a['percent']);

        $availableYears = [];
        foreach ($occupancyStats as $years) {
            foreach ($years as $year => $data) {
                $availableYears[$year] = true;
            }
        }
        ksort($availableYears);

        // Reindex para @json estÃ¡vel
        $monthlyStackedStats = array_values($monthlyStackedStats);

        return view('admin.vehicleUsages.usage', compact(
            'grouped',
            'occupancyStats',
            'yearlyStats',
            'monthlyStats',
            'availableYears',
            'timelineItems',
            'monthlyStackedStats'
        ));
    }
}





