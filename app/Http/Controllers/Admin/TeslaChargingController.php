<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyTeslaChargingRequest;
use App\Http\Requests\StoreTeslaChargingRequest;
use App\Http\Requests\UpdateTeslaChargingRequest;
use App\Models\TeslaCharging;
use App\Models\TvdeWeek;
use App\Services\TeslaChargingImporter;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class TeslaChargingController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('tesla_charging_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $normalizedTeslaIdentifier = "REPLACE(REPLACE(UPPER(COALESCE(tesla_chargings.license, '')), ' ', ''), '-', '')";
            $usageMatchCondition = "
                (
                    REPLACE(REPLACE(UPPER(COALESCE(vehicle_items.license_plate, '')), ' ', ''), '-', '') = {$normalizedTeslaIdentifier}
                    OR REPLACE(REPLACE(UPPER(COALESCE(vehicle_items.vin, '')), ' ', ''), '-', '') = {$normalizedTeslaIdentifier}
                )
                AND vehicle_usages.start_date <= tesla_chargings.datetime
                AND (vehicle_usages.end_date IS NULL OR vehicle_usages.end_date >= tesla_chargings.datetime)
                AND vehicle_usages.deleted_at IS NULL
                AND vehicle_items.deleted_at IS NULL
            ";

            $totalMatchesSubquery = "
                SELECT COUNT(*)
                FROM vehicle_usages
                INNER JOIN vehicle_items ON vehicle_items.id = vehicle_usages.vehicle_item_id
                WHERE {$usageMatchCondition}
            ";

            $nonNullDistinctDriversSubquery = "
                SELECT COUNT(DISTINCT vehicle_usages.driver_id)
                FROM vehicle_usages
                INNER JOIN vehicle_items ON vehicle_items.id = vehicle_usages.vehicle_item_id
                WHERE {$usageMatchCondition}
                  AND vehicle_usages.driver_id IS NOT NULL
            ";

            $resolvedDriverIdSubquery = "
                SELECT CASE
                    WHEN COUNT(DISTINCT vehicle_usages.driver_id) = 1 THEN MIN(vehicle_usages.driver_id)
                    ELSE NULL
                END
                FROM vehicle_usages
                INNER JOIN vehicle_items ON vehicle_items.id = vehicle_usages.vehicle_item_id
                WHERE {$usageMatchCondition}
                  AND vehicle_usages.driver_id IS NOT NULL
            ";

            $resolvedVehiclePlateSubquery = "
                SELECT CASE
                    WHEN COUNT(DISTINCT vehicle_usages.vehicle_item_id) = 1 THEN MIN(vehicle_items.license_plate)
                    ELSE NULL
                END
                FROM vehicle_usages
                INNER JOIN vehicle_items ON vehicle_items.id = vehicle_usages.vehicle_item_id
                WHERE {$usageMatchCondition}
            ";

            $validationIssueSql = "
                CASE
                    WHEN ({$resolvedDriverIdSubquery}) IS NOT NULL THEN 'Válido'
                    WHEN ({$totalMatchesSubquery}) = 0 THEN 'Sem utilização nesse momento'
                    WHEN ({$nonNullDistinctDriversSubquery}) = 0 THEN 'Viatura sem condutor atribuído nesse momento'
                    ELSE 'Conflito de utilizações nesse momento'
                END
            ";

            $query = TeslaCharging::query()
                ->leftJoin('tvde_weeks', 'tvde_weeks.id', '=', 'tesla_chargings.tvde_week_id')
                ->select([
                    'tesla_chargings.id',
                    'tesla_chargings.value',
                    'tesla_chargings.license',
                    'tesla_chargings.datetime',
                    'tesla_chargings.tvde_week_id',
                    'tesla_chargings.deleted_at',
                    'tvde_weeks.start_date as tvde_week_start_date',
                    DB::raw("({$resolvedDriverIdSubquery}) AS resolved_driver_id"),
                    DB::raw("({$resolvedVehiclePlateSubquery}) AS resolved_vehicle_license_plate"),
                    DB::raw("
                        (
                            SELECT drivers.name
                            FROM drivers
                            WHERE drivers.id = ({$resolvedDriverIdSubquery})
                              AND drivers.deleted_at IS NULL
                            LIMIT 1
                        ) AS resolved_driver_name
                    "),
                    DB::raw("
                        CASE
                            WHEN ({$resolvedDriverIdSubquery}) IS NOT NULL THEN 'exists'
                            ELSE 'does_not_exist'
                        END AS validation_status
                    "),
                    DB::raw("{$validationIssueSql} AS validation_issue"),
                ]);
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'tesla_charging_show';
                $editGate      = 'tesla_charging_edit';
                $deleteGate    = 'tesla_charging_delete';
                $crudRoutePart = 'tesla-chargings';

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
            $table->editColumn('value', function ($row) {
                return $row->value ? $row->value : '';
            });
            $table->addColumn('license', function ($row) {
                if (!$row->license) {
                    return '';
                }

                return $row->resolved_vehicle_license_plate
                    ? $row->license . ' / ' . $row->resolved_vehicle_license_plate
                    : $row->license;
            });

            $table->addColumn('datetime', function ($row) {
                return $row->datetime ? $row->datetime : '';
            });
            $table->addColumn('resolved_driver_name', function ($row) {
                return $row->resolved_driver_name ?: 'Não resolvido';
            });
            $table->addColumn('validation_status', function ($row) {
                return $row->validation_status === 'exists' ? 'Sim' : 'Não';
            });
            $table->addColumn('validation_issue', function ($row) {
                return $row->validation_issue ?: '';
            });

            $table->filterColumn('resolved_driver_name', function ($query, $keyword) use ($resolvedDriverIdSubquery) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }

                $query->whereExists(function ($subquery) use ($resolvedDriverIdSubquery, $keyword) {
                    $subquery->select(DB::raw(1))
                        ->from('drivers')
                        ->whereRaw("drivers.id = ({$resolvedDriverIdSubquery})")
                        ->whereNull('drivers.deleted_at')
                        ->where('drivers.name', 'like', "%{$keyword}%");
                });
            });

            $table->filterColumn('validation_status', function ($query, $keyword) use ($resolvedDriverIdSubquery) {
                $keyword = mb_strtolower(trim(str_replace(['^', '$'], '', $keyword)));

                if ($keyword === '') {
                    return;
                }

                if (in_array($keyword, ['sim', 's', '1', 'yes', 'exists', 'existe'], true)) {
                    $query->whereRaw("({$resolvedDriverIdSubquery}) IS NOT NULL");
                }

                if (in_array($keyword, ['nao', 'não', 'n', '0', 'no', 'does_not_exist'], true)) {
                    $query->whereRaw("({$resolvedDriverIdSubquery}) IS NULL");
                }
            });

            $table->filterColumn('validation_issue', function ($query, $keyword) use ($validationIssueSql) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }

                $query->whereRaw("{$validationIssueSql} LIKE ?", ["%{$keyword}%"]);
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        $tvdeWeeks = TvdeWeek::orderBy('start_date', 'desc')->get(['id', 'start_date', 'end_date']);
        $selectedWeekId = session()->get('tvde_week_id') ?: optional($tvdeWeeks->first())->id;

        return view('admin.teslaChargings.index', compact('selectedWeekId', 'tvdeWeeks'));
    }

    public function create()
    {
        abort_if(Gate::denies('tesla_charging_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.teslaChargings.create');
    }

    public function store(StoreTeslaChargingRequest $request)
    {
        $teslaCharging = TeslaCharging::create($request->all());

        return redirect()->route('admin.tesla-chargings.index');
    }

    public function edit(TeslaCharging $teslaCharging)
    {
        abort_if(Gate::denies('tesla_charging_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.teslaChargings.edit', compact('teslaCharging'));
    }

    public function update(UpdateTeslaChargingRequest $request, TeslaCharging $teslaCharging)
    {
        $teslaCharging->update($request->all());

        return redirect()->route('admin.tesla-chargings.index');
    }

    public function show(TeslaCharging $teslaCharging)
    {
        abort_if(Gate::denies('tesla_charging_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $teslaCharging->load('tvde_week');
        $validation = $teslaCharging->resolveUsageValidation();

        return view('admin.teslaChargings.show', compact('teslaCharging', 'validation'));
    }

    public function destroy(TeslaCharging $teslaCharging)
    {
        abort_if(Gate::denies('tesla_charging_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $teslaCharging->delete();

        return back();
    }

    public function massDestroy(MassDestroyTeslaChargingRequest $request)
    {
        $teslaChargings = TeslaCharging::find(request('ids'));

        foreach ($teslaChargings as $teslaCharging) {
            $teslaCharging->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function importReport(Request $request, TeslaChargingImporter $importer)
    {
        abort_if(Gate::denies('tesla_charging_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'tvde_week_id' => ['required', 'integer', 'exists:tvde_weeks,id'],
            'report_file' => ['required', 'file'],
        ], [], [
            'tvde_week_id' => 'Semana',
            'report_file' => 'Ficheiro',
        ]);

        try {
            $rows = $importer->import(
                $data['report_file']->getRealPath(),
                $data['report_file']->getClientOriginalName(),
                (int) $data['tvde_week_id']
            );
        } catch (RuntimeException $exception) {
            return back()
                ->with('open_import_panel', 'tesla')
                ->withInput()
                ->withErrors(['report_file' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.tesla-chargings.index')
            ->with('open_import_panel', 'tesla')
            ->with('message', "Import Tesla Charging concluído com {$rows} linhas.");
    }
}
