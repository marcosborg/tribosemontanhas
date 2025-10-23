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

            $query = VehicleUsage::with(['driver', 'vehicle_item'])->select(sprintf('%s.*', (new VehicleUsage)->table));

            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'vehicle_usage_show';
                $editGate      = 'vehicle_usage_edit';
                $deleteGate    = 'vehicle_usage_delete';
                $crudRoutePart = 'vehicle-usages';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->addColumn('driver_name', function ($row) {
                return $row->driver ? $row->driver->name : '';
            });

            $table->addColumn('vehicle_item_license_plate', function ($row) {
                return $row->vehicle_item ? $row->vehicle_item->license_plate : '';
            });

            $table->editColumn('usage_exceptions', function ($row) {
                return $row->usage_exceptions ? VehicleUsage::USAGE_EXCEPTIONS_RADIO[$row->usage_exceptions] : '';
            });

            $table->rawColumns(['actions', 'placeholder', 'driver', 'vehicle_item']);

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
        // As datas estão validadas no formato Y-m-d H:i:s, podemos usá-las diretamente
        $startDate = $request->start_date;
        $endDate   = $request->end_date;

        // Criar SEMPRE o novo registo
        $newUsage = VehicleUsage::create($request->all());

        // Verificar sobreposição com outros registos (excluindo o registo recém-criado)
        $hasOverlap = VehicleUsage::where('vehicle_item_id', $request->vehicle_item_id)
            ->where('id', '!=', $newUsage->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate);
            })
            ->first();

        if ($hasOverlap) {
            return redirect()->route('admin.vehicle-usages.index')
                ->with('error_message', "Utilização criada com sucesso (ID {$newUsage->id}), mas sobrepõe a utilização existente com ID {$hasOverlap->id}.");
        }

        return redirect()->route('admin.vehicle-usages.index')
            ->with('success', "Utilização criada com sucesso (ID {$newUsage->id}).");
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
        // --- Carrega usos, agrupado por matrícula ---
        $usages = VehicleUsage::with(['vehicle_item', 'driver'])
            ->orderBy('start_date')
            ->get();

        $grouped = $usages->groupBy('vehicle_item.license_plate');

        $occupancyStats = [];
        $monthlyStats = [];
        $monthlyStackedStats = []; // para o gráfico
        $yearlyMap = [];

        // 1) Última semana com dados em tvde_activity -> tvde_week_id
        $lastActivity = TvdeActivity::query()
            ->orderByDesc('id')
            ->first();
        $lastWeekId = optional($lastActivity)->tvde_week_id;

        // 2) Intervalo da semana (fallback = semana corrente)
        $weekStart = now()->startOfWeek();
        $weekEnd   = now()->endOfWeek();

        if ($lastWeekId) {
            $tw = TvdeWeek::query()->find($lastWeekId);
            if ($tw && !empty($tw->start_date) && !empty($tw->end_date)) {
                try {
                    $weekStart = Carbon::parse($tw->start_date)->startOfDay();
                    $weekEnd   = Carbon::parse($tw->end_date)->endOfDay();
                } catch (\Throwable $e) { /* mantém fallback */
                }
            }
        }

        // 3) Mapa de rendas por matrícula (rentByPlate) via CurrentAccount.data.car_hire
        //    Pré-carrega todos os CurrentAccount da última semana e indexa por driver_id
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
                ->where('start_date', '<=', $weekEnd)     // começa antes de terminar a semana
                ->where('end_date', '>=', $weekStart)     // termina depois de começar a semana
                ->orderByDesc('end_date')
                ->first();

            $driverId = optional($usageInWeek)->driver_id;

            // Fallback: último usage com driver (se a semana não tiver intersecção)
            if (!$driverId) {
                $lastWithDriver = VehicleUsage::query()
                    ->where('vehicle_item_id', $vehicleItem->id)
                    ->whereNotNull('driver_id')
                    ->orderByDesc('end_date')
                    ->first();
                $driverId = optional($lastWithDriver)->driver_id;
            }

            // Tentar obter do CurrentAccount dessa semana: data->car_hire
            $rent = null;
            if ($driverId && isset($currentAccountsByDriver[$driverId])) {
                $payload = $currentAccountsByDriver[$driverId]->data;

                // Caso o campo não esteja com cast para array, decodifica
                if (is_string($payload)) {
                    $decoded = json_decode($payload, true);
                    $payload = is_array($decoded) ? $decoded : [];
                } elseif (!is_array($payload)) {
                    $payload = [];
                }

                // caminho principal: car_hire na raiz do JSON (segundo o teu exemplo)
                // (mantém fallback para earnings.car_hire se algum mais antigo tiver assim)
                $rent = data_get($payload, 'car_hire');
                if ($rent === null) {
                    $rent = data_get($payload, 'earnings.car_hire');
                }
            }

            // fallback final (visual) caso não haja CurrentAccount ou não tenha car_hire
            $rentByPlate[$plate] = is_numeric($rent) ? (float) $rent : 300;
        }

        // 4) Estatísticas (igual ao teu original), acrescentando 'rent' a cada monthKey
        foreach ($grouped as $plate => $usagesForVehicle) {
            $years = [];

            foreach ($usagesForVehicle as $usage) {
                $startRaw = $usage->getRawOriginal('start_date');
                $endRaw   = $usage->getRawOriginal('end_date');

                try {
                    $start = Carbon::createFromFormat('Y-m-d H:i:s', $startRaw);
                } catch (\Exception $e) {
                    \Log::error("Data inválida em VehicleUsage ID {$usage->id} (start_date): '{$startRaw}'");
                    continue;
                }

                try {
                    $end = Carbon::createFromFormat('Y-m-d H:i:s', $endRaw);
                } catch (\Exception $e) {
                    \Log::error("Data inválida em VehicleUsage ID {$usage->id} (end_date): '{$endRaw}'");
                    continue;
                }

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
                            'year'  => (string) $year,
                            'month' => str_pad($month, 2, '0', STR_PAD_LEFT),
                            'days'  => 0,
                        ];
                    }
                    $monthlyStats[$monthKey]['days']++;

                    if (!isset($monthlyStackedStats[$monthKey])) {
                        $monthlyStackedStats[$monthKey] = [
                            'label'       => $monthKey,
                            'plate'       => $plate,
                            'year'        => (string) $year,
                            'month'       => str_pad($month, 2, '0', STR_PAD_LEFT),
                            'usage'       => 0,
                            'maintenance' => 0,
                            'accident'    => 0,
                            'unassigned'  => 0,
                            'personal'    => 0,
                            'rent'        => $rentByPlate[$plate] ?? null, // valor do CurrentAccount
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
                    'used'    => $usedCount,
                    'total'   => $totalDays,
                    'percent' => round(($usedCount / $totalDays) * 100, 2),
                ];

                $yearKey = "$plate ($year)";
                if (!isset($yearlyMap[$yearKey])) {
                    $yearlyMap[$yearKey] = [
                        'label'        => $yearKey,
                        'year'         => (string) $year,
                        'totalPercent' => 0,
                        'months'       => 0,
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
                'label'   => $entry['label'],
                'year'    => $entry['year'],
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

        // Reindex para @json estável
        $monthlyStackedStats = array_values($monthlyStackedStats);

        return view('admin.vehicleUsages.usage', compact(
            'grouped',
            'occupancyStats',
            'yearlyStats',
            'monthlyStats',
            'availableYears',
            'monthlyStackedStats'
        ));
    }
}
