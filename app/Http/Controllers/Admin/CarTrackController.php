<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyCarTrackRequest;
use App\Http\Requests\StoreCarTrackRequest;
use App\Http\Requests\UpdateCarTrackRequest;
use App\Models\CarTrack;
use App\Models\TvdeWeek;
use Gate;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CarTrackController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('car_track_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = CarTrack::query()
                ->leftJoin('tvde_weeks', 'tvde_weeks.id', '=', 'car_tracks.tvde_week_id')
                ->select([
                    'car_tracks.id',
                    'car_tracks.date',
                    'car_tracks.license_plate',
                    'car_tracks.value',
                    'tvde_weeks.start_date as tvde_week_start_date',
                    'car_tracks.deleted_at',
                    DB::raw("
                        (
                            SELECT d.name
                            FROM vehicle_usages vu
                            INNER JOIN vehicle_items vi ON vi.id = vu.vehicle_item_id
                            INNER JOIN drivers d        ON d.id = vu.driver_id
                            WHERE REPLACE(UPPER(vi.license_plate), ' ', '') = REPLACE(UPPER(car_tracks.license_plate), ' ', '')
                              AND vu.deleted_at IS NULL
                              AND vi.deleted_at IS NULL
                              AND d.deleted_at  IS NULL
                              AND DATE(vu.start_date) <= DATE(car_tracks.date)
                              AND (vu.end_date IS NULL OR DATE(vu.end_date) >= DATE(car_tracks.date))
                            ORDER BY vu.start_date DESC
                            LIMIT 1
                        ) AS driver_name
                    "),
                ]);

            $table = DataTables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'car_track_show';
                $editGate      = 'car_track_edit';
                $deleteGate    = 'car_track_delete';
                $crudRoutePart = 'car-tracks';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', fn($row) => $row->id ?: '');
            $table->editColumn('date', fn($row) => $row->date ?: '');
            $table->editColumn('license_plate', fn($row) => $row->license_plate ?: '');
            $table->editColumn('value', fn($row) => $row->value ?: '');
            $table->editColumn('tvde_week_start_date', fn($row) => $row->tvde_week_start_date ?: '');
            $table->addColumn('driver_name', fn($row) => $row->driver_name ?: 'Não existe');

            // Filtros server-side
            $table->filterColumn('driver_name', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') return;

                $query->whereExists(function ($q) use ($keyword) {
                    $q->select(DB::raw(1))
                        ->from('vehicle_usages as vu')
                        ->join('vehicle_items as vi', 'vi.id', '=', 'vu.vehicle_item_id')
                        ->join('drivers as d', 'd.id', '=', 'vu.driver_id')
                        ->whereRaw("REPLACE(UPPER(vi.license_plate), ' ', '') = REPLACE(UPPER(car_tracks.license_plate), ' ', '')")
                        ->whereNull('vu.deleted_at')
                        ->whereNull('vi.deleted_at')
                        ->whereNull('d.deleted_at')
                        ->whereRaw('DATE(vu.start_date) <= DATE(car_tracks.date)')
                        ->whereRaw('(vu.end_date IS NULL OR DATE(vu.end_date) >= DATE(car_tracks.date))')
                        ->where('d.name', 'like', "%{$keyword}%");
                });
            });

            $table->filterColumn('car_tracks.date', function ($query, $keyword) {
                $kw = trim($keyword);
                if ($kw === '') return;
                $query->whereRaw('DATE(car_tracks.date) LIKE ?', ["%{$kw}%"]);
            });

            $table->filterColumn('tvde_weeks.start_date', function ($query, $keyword) {
                $kw = trim($keyword);
                if ($kw === '') return;
                $query->where('tvde_weeks.start_date', 'like', "%{$kw}%");
            });

            $table->filterColumn('car_tracks.license_plate', function ($query, $keyword) {
                $kw = trim($keyword);
                if ($kw === '') return;
                $kw = strtoupper(str_replace([' ', '-'], '', $kw));
                $query->whereRaw(
                    "REPLACE(REPLACE(UPPER(car_tracks.license_plate), ' ', ''), '-', '') LIKE ?",
                    ["%{$kw}%"]
                );
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.carTracks.index');
    }

    public function create()
    {
        abort_if(Gate::denies('car_track_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvde_weeks = TvdeWeek::pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.carTracks.create', compact('tvde_weeks'));
    }

    public function store(StoreCarTrackRequest $request)
    {
        $carTrack = CarTrack::create($request->all());

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
}
