<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyTeslaChargingRequest;
use App\Http\Requests\StoreTeslaChargingRequest;
use App\Http\Requests\UpdateTeslaChargingRequest;
use App\Models\Driver;
use App\Models\TeslaCharging;
use App\Models\TvdeWeek;
use App\Services\TeslaChargingImporter;
use Gate;
use Illuminate\Http\Request;
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
            $query = TeslaCharging::select(sprintf('%s.*', (new TeslaCharging)->table));
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
                return $row->license ? $row->license : '';
            });

            $table->addColumn('datetime', function ($row) {
                return $row->datetime ? $row->datetime : '';
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

        return view('admin.teslaChargings.show', compact('teslaCharging'));
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
