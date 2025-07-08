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
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class CarTrackController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('car_track_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = CarTrack::select(sprintf('%s.*', (new CarTrack)->table));
            $table = Datatables::of($query);

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

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->addColumn('date', function ($row) {
                return $row->date ? $row->date : '';
            });

            $table->editColumn('license_plate', function ($row) {
                return $row->license_plate ? $row->license_plate : '';
            });
            $table->editColumn('value', function ($row) {
                return $row->value ? $row->value : '';
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.carTracks.index');
    }

    public function create()
    {
        abort_if(Gate::denies('car_track_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.carTracks.create');
    }

    public function store(StoreCarTrackRequest $request)
    {
        $carTrack = CarTrack::create($request->all());

        return redirect()->route('admin.car-tracks.index');
    }

    public function edit(CarTrack $carTrack)
    {
        abort_if(Gate::denies('car_track_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.carTracks.edit', compact('carTrack'));
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
