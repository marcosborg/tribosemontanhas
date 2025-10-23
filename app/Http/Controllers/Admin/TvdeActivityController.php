<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyTvdeActivityRequest;
use App\Http\Requests\StoreTvdeActivityRequest;
use App\Http\Requests\UpdateTvdeActivityRequest;
use App\Models\Company;
use App\Models\TvdeActivity;
use App\Models\TvdeOperator;
use App\Models\TvdeWeek;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TvdeActivityController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('tvde_activity_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = TvdeActivity::query()
                ->leftJoin('tvde_weeks', 'tvde_weeks.id', '=', 'tvde_activities.tvde_week_id')
                ->leftJoin('tvde_operators', 'tvde_operators.id', '=', 'tvde_activities.tvde_operator_id')
                ->leftJoin('companies', 'companies.id', '=', 'tvde_activities.company_id')
                ->select([
                    'tvde_activities.*',
                    'tvde_weeks.start_date as tvde_week_start_date',
                    'tvde_operators.name as tvde_operator_name',
                    'companies.name as company_name',
                    // Também projetamos a flag para poder ordenar sem recalcular no PHP
                    \DB::raw("
                    CASE
                      WHEN LOWER(tvde_operators.name) LIKE '%uber%' THEN
                        EXISTS(
                          SELECT 1 FROM drivers d
                          WHERE d.deleted_at IS NULL
                            AND d.uber_uuid = tvde_activities.driver_code
                        )
                      WHEN LOWER(tvde_operators.name) LIKE '%bolt%' THEN
                        EXISTS(
                          SELECT 1 FROM drivers d
                          WHERE d.deleted_at IS NULL
                            AND d.bolt_name = tvde_activities.driver_code
                        )
                      ELSE 0
                    END AS exists_flag
                "),
                ]);

            $table = \Yajra\DataTables\Facades\DataTables::of($query);

            // Ações
            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');
            $table->editColumn('actions', function ($row) {
                $viewGate      = 'tvde_activity_show';
                $editGate      = 'tvde_activity_edit';
                $deleteGate    = 'tvde_activity_delete';
                $crudRoutePart = 'tvde-activities';
                return view('partials.datatablesActions', compact('viewGate', 'editGate', 'deleteGate', 'crudRoutePart', 'row'));
            });

            // Campos simples
            $table->editColumn('id', fn($row) => $row->id ?? '');
            $table->editColumn('tvde_week_start_date', fn($row) => $row->tvde_week_start_date ?? '');
            $table->editColumn('tvde_operator_name', fn($row) => $row->tvde_operator_name ?? '');
            $table->editColumn('company_name', fn($row) => $row->company_name ?? '');
            $table->editColumn('driver_code', fn($row) => $row->driver_code ?? '');
            $table->editColumn('gross', fn($row) => $row->gross ?? '');
            $table->editColumn('net', fn($row) => $row->net ?? '');

            // Texto “Existe / Não existe”
            $table->addColumn('exists_text', fn($row) => $row->exists_flag ? 'Existe' : 'Não existe');

            // ===== Filtro por coluna (header) para exists_text — AGORA COM WHERE RAW =====
            $table->filterColumn('exists_text', function ($q, $keyword) {
                $k = \Illuminate\Support\Str::lower(trim($keyword));
                if ($k === '') return;
                // normalização básica
                $k = str_replace(['ã', 'á', 'â'], 'a', $k);

                // mapear para 1/0
                $want = null;
                if (preg_match('/^(1|sim|yes|exis)/', $k) || $k === 'existe') {
                    $want = 1;
                } elseif (preg_match('/^(0|nao|no)/', $k) || str_contains($k, 'nao exis') || $k === 'nao existe' || $k === 'não existe') {
                    $want = 0;
                } elseif (str_contains($k, 'exis')) {
                    $want = 1;
                } elseif (str_contains($k, 'nao') || str_contains($k, 'não')) {
                    $want = 0;
                }

                if ($want === null) return;

                // usar o MESMO CASE no WHERE (evita HAVING)
                $case = "
                (CASE
                  WHEN LOWER(tvde_operators.name) LIKE '%uber%' THEN
                    EXISTS(SELECT 1 FROM drivers d WHERE d.deleted_at IS NULL AND d.uber_uuid = tvde_activities.driver_code)
                  WHEN LOWER(tvde_operators.name) LIKE '%bolt%' THEN
                    EXISTS(SELECT 1 FROM drivers d WHERE d.deleted_at IS NULL AND d.bolt_name = tvde_activities.driver_code)
                  ELSE 0
                 END)
            ";
                $q->whereRaw("$case = ?", [$want]);
            });

            // Ordenar pela flag usando o mesmo CASE (sem HAVING)
            $table->orderColumn('exists_text', function ($q, $order) {
                $case = "
                (CASE
                  WHEN LOWER(tvde_operators.name) LIKE '%uber%' THEN
                    EXISTS(SELECT 1 FROM drivers d WHERE d.deleted_at IS NULL AND d.uber_uuid = tvde_activities.driver_code)
                  WHEN LOWER(tvde_operators.name) LIKE '%bolt%' THEN
                    EXISTS(SELECT 1 FROM drivers d WHERE d.deleted_at IS NULL AND d.bolt_name = tvde_activities.driver_code)
                  ELSE 0
                 END)
            ";
                $q->orderByRaw("$case $order");
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.tvdeActivities.index');
    }


    public function create()
    {
        abort_if(Gate::denies('tvde_activity_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvde_weeks = TvdeWeek::orderBy('start_date', 'desc')->get()->pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tvde_operators = TvdeOperator::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $companies = Company::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.tvdeActivities.create', compact('companies', 'tvde_operators', 'tvde_weeks'));
    }

    public function store(StoreTvdeActivityRequest $request)
    {
        $tvdeActivity = TvdeActivity::create($request->all());

        return redirect()->route('admin.tvde-activities.index');
    }

    public function edit(TvdeActivity $tvdeActivity)
    {
        abort_if(Gate::denies('tvde_activity_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvde_weeks = TvdeWeek::orderBy('start_date', 'desc')->get()->pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tvde_operators = TvdeOperator::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $companies = Company::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tvdeActivity->load('tvde_week', 'tvde_operator', 'company');

        return view('admin.tvdeActivities.edit', compact('companies', 'tvdeActivity', 'tvde_operators', 'tvde_weeks'));
    }

    public function update(UpdateTvdeActivityRequest $request, TvdeActivity $tvdeActivity)
    {
        $tvdeActivity->update($request->all());

        return redirect()->route('admin.tvde-activities.index');
    }

    public function show(TvdeActivity $tvdeActivity)
    {
        abort_if(Gate::denies('tvde_activity_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvdeActivity->load('tvde_week', 'tvde_operator', 'company');

        return view('admin.tvdeActivities.show', compact('tvdeActivity'));
    }

    public function destroy(TvdeActivity $tvdeActivity)
    {
        abort_if(Gate::denies('tvde_activity_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvdeActivity->delete();

        return back();
    }

    public function massDestroy(MassDestroyTvdeActivityRequest $request)
    {
        $tvdeActivities = TvdeActivity::find(request('ids'));

        foreach ($tvdeActivities as $tvdeActivity) {
            $tvdeActivity->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function deleteFilter(Request $request)
    {

        $request->validate([
            'week_filter' => 'required'
        ]);

        if ($request->company_filter) {
            $tvde_activities = TvdeActivity::where([
                'tvde_week_id' => $request->week_filter,
                'company_id' => $request->company_filter
            ]);
        } else {
            $tvde_activities = TvdeActivity::where([
                'tvde_week_id' => $request->week_filter
            ]);
        }

        $tvde_activities->delete();

        return redirect()->back()->with('message', 'Eliminado com sucesso');
    }
}
