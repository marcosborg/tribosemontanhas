<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyCarTrackRequest;
use App\Http\Requests\StoreCarTrackRequest;
use App\Http\Requests\UpdateCarTrackRequest;
use App\Models\CarTrack;
use App\Models\TvdeWeek;
use App\Services\CarTrackClassificationService;
use App\Services\CarTrackImporter;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class CarTrackController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('car_track_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = CarTrack::query()
                ->leftJoin('tvde_weeks', 'tvde_weeks.id', '=', 'car_tracks.tvde_week_id')
                ->leftJoin('drivers', 'drivers.id', '=', 'car_tracks.driver_id')
                ->leftJoin('companies', 'companies.id', '=', 'car_tracks.company_id')
                ->select([
                    'car_tracks.id',
                    'car_tracks.date',
                    'car_tracks.license_plate',
                    'car_tracks.value',
                    'car_tracks.classification_status',
                    'car_tracks.classification_reason',
                    'tvde_weeks.start_date as tvde_week_start_date',
                    DB::raw("
                        COALESCE(
                            drivers.name,
                            (
                                SELECT d.name
                                FROM vehicle_usages vu
                                INNER JOIN vehicle_items vi ON vi.id = vu.vehicle_item_id
                                INNER JOIN drivers d ON d.id = vu.driver_id
                                WHERE REPLACE(REPLACE(UPPER(vi.license_plate), ' ', ''), '-', '') = REPLACE(REPLACE(UPPER(car_tracks.license_plate), ' ', ''), '-', '')
                                  AND vu.deleted_at IS NULL
                                  AND vi.deleted_at IS NULL
                                  AND d.deleted_at IS NULL
                                  AND vu.start_date <= car_tracks.date
                                  AND (vu.end_date IS NULL OR vu.end_date >= car_tracks.date)
                                ORDER BY vu.start_date DESC
                                LIMIT 1
                            )
                        ) AS driver_name
                    "),
                    'companies.name as company_name',
                    'car_tracks.deleted_at',
                ]);

            $table = DataTables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'car_track_show';
                $editGate = 'car_track_edit';
                $deleteGate = 'car_track_delete';
                $crudRoutePart = 'car-tracks';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', fn ($row) => $row->id ?: '');
            $table->editColumn('date', fn ($row) => $row->date ?: '');
            $table->editColumn('license_plate', fn ($row) => $row->license_plate ?: '');
            $table->editColumn('value', fn ($row) => $row->value ?: '');
            $table->editColumn('tvde_week_start_date', fn ($row) => $row->tvde_week_start_date ?: '');
            $table->addColumn('driver_name', fn ($row) => $row->driver_name ?: '');
            $table->addColumn('company_name', fn ($row) => $row->company_name ?: '');
            $table->addColumn('classification_destination', fn ($row) => $this->classificationDestinationLabel($row->classification_status));
            $table->addColumn('classification_reason_label', fn ($row) => $this->classificationReasonLabel($row->classification_reason));

            $table->filterColumn('driver_name', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword !== '') {
                    $query->where('drivers.name', 'like', "%{$keyword}%");
                }
            });

            $table->filterColumn('company_name', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword !== '') {
                    $query->where('companies.name', 'like', "%{$keyword}%");
                }
            });

            $table->filterColumn('classification_destination', function ($query, $keyword) {
                $kw = mb_strtolower(trim(str_replace(['^', '$'], '', $keyword)));

                if (in_array($kw, ['motorista', CarTrackClassificationService::STATUS_DRIVER], true)) {
                    $query->where('car_tracks.classification_status', CarTrackClassificationService::STATUS_DRIVER);
                } elseif (in_array($kw, ['empresa', CarTrackClassificationService::STATUS_COMPANY], true)) {
                    $query->where('car_tracks.classification_status', CarTrackClassificationService::STATUS_COMPANY);
                } elseif (in_array($kw, ['validacao manual', 'validação manual', 'manual', CarTrackClassificationService::STATUS_MANUAL], true)) {
                    $query->where('car_tracks.classification_status', CarTrackClassificationService::STATUS_MANUAL);
                }
            });

            $table->filterColumn('classification_reason_label', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword !== '') {
                    $query->where('car_tracks.classification_reason', 'like', "%{$keyword}%");
                }
            });

            $table->filterColumn('car_tracks.date', function ($query, $keyword) {
                $kw = trim($keyword);
                if ($kw !== '') {
                    $query->whereRaw('DATE(car_tracks.date) LIKE ?', ["%{$kw}%"]);
                }
            });

            $table->filterColumn('tvde_weeks.start_date', function ($query, $keyword) {
                $kw = trim($keyword);
                if ($kw !== '') {
                    $query->where('tvde_weeks.start_date', 'like', "%{$kw}%");
                }
            });

            $table->filterColumn('car_tracks.license_plate', function ($query, $keyword) {
                $kw = trim($keyword);
                if ($kw === '') {
                    return;
                }

                $kw = strtoupper(str_replace([' ', '-'], '', $kw));
                $query->whereRaw(
                    "REPLACE(REPLACE(UPPER(car_tracks.license_plate), ' ', ''), '-', '') LIKE ?",
                    ["%{$kw}%"]
                );
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        $tvdeWeeks = TvdeWeek::orderBy('start_date', 'desc')->get(['id', 'start_date', 'end_date']);
        $selectedWeekId = session()->get('tvde_week_id') ?: optional($tvdeWeeks->first())->id;

        return view('admin.carTracks.index', compact('selectedWeekId', 'tvdeWeeks'));
    }

    public function create()
    {
        abort_if(Gate::denies('car_track_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvde_weeks = TvdeWeek::pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.carTracks.create', compact('tvde_weeks'));
    }

    public function store(StoreCarTrackRequest $request)
    {
        CarTrack::create($request->all());

        return redirect()->route('admin.car-tracks.index');
    }

    public function edit(CarTrack $carTrack)
    {
        abort_if(Gate::denies('car_track_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvde_weeks = TvdeWeek::pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        $carTrack->load('tvde_week');

        return view('admin.carTracks.edit', compact('carTrack', 'tvde_weeks'));
    }

    public function update(UpdateCarTrackRequest $request, CarTrack $carTrack)
    {
        $carTrack->update($request->all());

        return redirect()->route('admin.car-tracks.index');
    }

    public function show(CarTrack $carTrack)
    {
        abort_if(Gate::denies('car_track_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.carTracks.show', compact('carTrack'));
    }

    public function destroy(CarTrack $carTrack)
    {
        abort_if(Gate::denies('car_track_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $carTrack->delete();

        return back();
    }

    public function massDestroy(MassDestroyCarTrackRequest $request)
    {
        $carTracks = CarTrack::find(request('ids'));

        foreach ($carTracks as $carTrack) {
            $carTrack->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function importViaVerde(Request $request, CarTrackImporter $importer)
    {
        abort_if(Gate::denies('car_track_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'tvde_week_id' => ['required', 'integer', 'exists:tvde_weeks,id'],
            'report_file' => ['required', 'file'],
        ], [], [
            'tvde_week_id' => 'Semana',
            'report_file' => 'Ficheiro',
        ]);

        try {
            $summary = $importer->import(
                $data['report_file']->getRealPath(),
                $data['report_file']->getClientOriginalName(),
                (int) $data['tvde_week_id']
            );
        } catch (RuntimeException $exception) {
            return back()
                ->with('open_import_panel', 'via_verde')
                ->withInput()
                ->withErrors(['report_file' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.car-tracks.index')
            ->with('open_import_panel', 'via_verde')
            ->with('message', sprintf(
                'Import Via Verde concluido com %d linhas: %d motorista, %d empresa, %d validacao manual.',
                $summary['total'],
                $summary['driver'],
                $summary['company'],
                $summary['manual']
            ));
    }

    private function classificationDestinationLabel(?string $status): string
    {
        return match ($status) {
            CarTrackClassificationService::STATUS_COMPANY => 'Empresa',
            CarTrackClassificationService::STATUS_MANUAL => 'Validacao manual',
            default => 'Motorista',
        };
    }

    private function classificationReasonLabel(?string $reason): string
    {
        return [
            'normal_driver' => 'Motorista responsavel',
            'management_vehicle' => 'Viatura de gestao',
            'personal_usage' => 'Utilizacao pessoal',
            'missing_vehicle' => 'Matricula/viatura nao encontrada',
            'missing_usage' => 'Sem utilizacao ativa',
            'missing_driver' => 'Sem motorista imputavel',
            'missing_company' => 'Sem empresa na viatura',
        ][$reason] ?? '';
    }
}
