<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyCombustionTransactionRequest;
use App\Http\Requests\StoreCombustionTransactionRequest;
use App\Http\Requests\UpdateCombustionTransactionRequest;
use App\Models\CombustionTransaction;
use App\Models\TvdeWeek;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class CombustionTransactionController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('combustion_transaction_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = CombustionTransaction::with(['tvde_week'])
                ->select(sprintf('%s.*', (new CombustionTransaction)->table));

            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'combustion_transaction_show';
                $editGate = 'combustion_transaction_edit';
                $deleteGate = 'combustion_transaction_delete';
                $crudRoutePart = 'combustion-transactions';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', fn($row) => $row->id ?: '');

            // Relacional (sem alterar o select)
            $table->addColumn('tvde_week_start_date', fn($row) => $row->tvde_week?->start_date ?: '');

            $table->editColumn('card', fn($row) => $row->card ?: '');

            $table->addColumn('exist', function ($row) {
                if (!$row->card) {
                    return 'Sem cartão';
                }

                $exists = \App\Models\Driver::where('card_id', function ($q) use ($row) {
                    $q->select('id')
                        ->from('cards')
                        ->where('code', $row->card)
                        ->limit(1);
                })
                    ->orWhereHas('cards', function ($q) use ($row) {
                        $q->where('code', $row->card);
                    })
                    ->exists();

                return $exists ? 'Sim' : 'Não';
            });

            $table->editColumn('amount', fn($row) => $row->amount ?: '');
            $table->editColumn('total', fn($row) => $row->total ?: '');

            // ---- Filtros server-side por coluna ----
            $table->filterColumn('tvde_week_start_date', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->whereHas('tvde_week', fn($qq) => $qq->where('start_date', 'like', "%{$k}%"));
            });

            $table->filterColumn('card', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->where('card', 'like', "%{$k}%");
            });

            $table->filterColumn('amount', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->where('amount', 'like', "%{$k}%");
            });

            $table->filterColumn('total', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->where('total', 'like', "%{$k}%");
            });

            $table->filterColumn('exist', function ($query, $keyword) {
                $kw = mb_strtolower(trim(str_replace(['^', '$'], '', $keyword)));

                if ($kw === '') {
                    return;
                }

                // Subquery que verifica se há algum driver com este cartão
                $subquery = function ($q) {
                    $q->select(DB::raw(1))
                        ->from('drivers as d')
                        ->leftJoin('cards as c_main', 'c_main.id', '=', 'd.card_id')
                        ->leftJoin('card_driver as cd', 'cd.driver_id', '=', 'd.id')
                        ->leftJoin('cards as c_pivot', 'c_pivot.id', '=', 'cd.card_id')
                        ->whereNull('d.deleted_at')
                        ->where(function ($qq) {
                            $qq->whereRaw('c_main.code = combustion_transactions.card')
                                ->orWhereRaw('c_pivot.code = combustion_transactions.card');
                        });
                };

                // "Sim" → existem drivers com esse cartão
                if (in_array($kw, ['sim', 's', '1', 'yes'])) {
                    $query->whereExists($subquery);
                }

                // "Não" → não existe nenhum driver com esse cartão
                if (in_array($kw, ['nao', 'não', 'n', '0', 'no'])) {
                    $query->whereNotExists($subquery);
                }
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.combustionTransactions.index');
    }

    public function create()
    {
        abort_if(Gate::denies('combustion_transaction_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvde_weeks = TvdeWeek::orderBy('start_date', 'desc')->get()->pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.combustionTransactions.create', compact('tvde_weeks'));
    }

    public function store(StoreCombustionTransactionRequest $request)
    {
        $combustionTransaction = CombustionTransaction::create($request->all());

        return redirect()->route('admin.combustion-transactions.index');
    }

    public function edit(CombustionTransaction $combustionTransaction)
    {
        abort_if(Gate::denies('combustion_transaction_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvde_weeks = TvdeWeek::orderBy('start_date', 'desc')->get()->pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        $combustionTransaction->load('tvde_week');

        return view('admin.combustionTransactions.edit', compact('combustionTransaction', 'tvde_weeks'));
    }

    public function update(UpdateCombustionTransactionRequest $request, CombustionTransaction $combustionTransaction)
    {
        $combustionTransaction->update($request->all());

        return redirect()->route('admin.combustion-transactions.index');
    }

    public function show(CombustionTransaction $combustionTransaction)
    {
        abort_if(Gate::denies('combustion_transaction_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $combustionTransaction->load('tvde_week');

        return view('admin.combustionTransactions.show', compact('combustionTransaction'));
    }

    public function destroy(CombustionTransaction $combustionTransaction)
    {
        abort_if(Gate::denies('combustion_transaction_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $combustionTransaction->delete();

        return back();
    }

    public function massDestroy(MassDestroyCombustionTransactionRequest $request)
    {
        $combustionTransactions = CombustionTransaction::find(request('ids'));

        foreach ($combustionTransactions as $combustionTransaction) {
            $combustionTransaction->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
