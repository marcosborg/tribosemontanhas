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
            // Base
            $base = TvdeActivity::query()
                ->with(['tvde_week', 'tvde_operator', 'company'])
                ->leftJoin('tvde_operators', 'tvde_operators.id', '=', 'tvde_activities.tvde_operator_id')
                ->select([
                    'tvde_activities.*',

                    // === Flag de existência (1/0) com base no operador ===
                    DB::raw("
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

            // Filtro por empresa (mantendo a tua lógica)
            if (session()->get('company_id')) {
                $base->where('tvde_activities.company_id', session()->get('company_id'));
            }

            $table = Datatables::of($base);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'tvde_activity_show';
                $editGate      = 'tvde_activity_edit';
                $deleteGate    = 'tvde_activity_delete';
                $crudRoutePart = 'tvde-activities';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', fn($row) => $row->id ?: '');
            $table->addColumn('tvde_week_start_date', fn($row) => $row->tvde_week?->start_date ?: '');
            $table->addColumn('tvde_operator_name', fn($row) => $row->tvde_operator?->name ?: '');
            $table->addColumn('company_name', fn($row) => $row->company?->name ?: '');
            $table->editColumn('driver_code', fn($row) => $row->driver_code ?: '');
            $table->editColumn('gross', fn($row) => $row->gross ?: '');
            $table->editColumn('net', fn($row) => $row->net ?: '');

            // Coluna visual (badge) com base no exists_flag
            $table->addColumn('exists', function ($row) {
                return $row->exists_flag
                    ? '<span class="label label-success">Existe</span>'
                    : '<span class="label label-danger">Não existe</span>';
            });

            // Permitir filtrar por “Existe / Não existe” usando o alias via HAVING
            $table->filterColumn('exists', function ($query, $keyword) {
                $k = Str::lower(trim($keyword));

                if (in_array($k, ['1', 'existe', 'sim', 'yes'])) {
                    $query->havingRaw('exists_flag = 1');
                } elseif (in_array($k, ['0', 'não existe', 'nao existe', 'não', 'nao', 'no'])) {
                    $query->havingRaw('exists_flag = 0');
                }
                // Se vier vazio, não aplica filtro
            });

            // (Opcional) permitir ordenar por existe/não existe
            $table->orderColumn('exists', function ($query, $order) {
                $query->orderBy('exists_flag', $order);
            });

            $table->rawColumns(['actions', 'placeholder', 'exists']);

            return $table->make(true);
        }

        $tvde_weeks = TvdeWeek::all();
        $companies  = Company::all();

        return view('admin.tvdeActivities.index', compact('tvde_weeks', 'companies'));
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
