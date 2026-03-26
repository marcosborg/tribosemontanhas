<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyCardRequest;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Models\Card;
use App\Models\Company;
use Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class CardController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('card_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = $this->buildCardsQuery();

            $table = DataTables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'card_show';
                $editGate = 'card_edit';
                $deleteGate = 'card_delete';
                $crudRoutePart = 'cards';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', fn ($row) => $row->id ?: '');
            $table->editColumn('type', function ($row) {
                return $row->type ? (Card::TYPE_RADIO[$row->type] ?? $row->type) : '';
            });
            $table->editColumn('code', fn ($row) => $row->code ?: '');
            $table->addColumn('company_name', fn ($row) => $row->company?->name ?: '');
            $table->addColumn('assigned_driver_names', fn ($row) => $this->formatAssignedDriverNames($row));

            $table->filterColumn('id', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }

                $query->where('cards.id', 'like', "%{$keyword}%");
            });

            $table->filterColumn('type', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }

                $keys = collect(Card::TYPE_RADIO)
                    ->filter(function ($label, $key) use ($keyword) {
                        return str_contains(mb_strtolower($label), mb_strtolower($keyword))
                            || str_contains(mb_strtolower($key), mb_strtolower($keyword));
                    })
                    ->keys()
                    ->all();

                $query->where(function (Builder $builder) use ($keyword, $keys) {
                    $builder->where('cards.type', 'like', "%{$keyword}%");

                    if ($keys !== []) {
                        $builder->orWhereIn('cards.type', $keys);
                    }
                });
            });

            $table->filterColumn('code', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }

                $query->where('cards.code', 'like', "%{$keyword}%");
            });

            $table->filterColumn('company_name', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }

                $query->whereHas('company', fn (Builder $builder) => $builder->where('name', 'like', "%{$keyword}%"));
            });

            $table->filterColumn('assigned_driver_names', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }

                $query->where(function (Builder $builder) use ($keyword) {
                    $builder
                        ->whereHas('primaryDrivers', fn (Builder $driverQuery) => $driverQuery->where('name', 'like', "%{$keyword}%"))
                        ->orWhereHas('drivers', fn (Builder $driverQuery) => $driverQuery->where('name', 'like', "%{$keyword}%"));
                });
            });

            $table->filter(function (Builder $query) use ($request) {
                $this->applyCardGlobalSearch($query, (string) $request->input('search.value', ''));
            }, false);

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.cards.index');
    }

    public function create()
    {
        abort_if(Gate::denies('card_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companies = Company::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.cards.create', compact('companies'));
    }

    public function store(StoreCardRequest $request)
    {
        $card = Card::create($request->all());

        return redirect()->route('admin.cards.index');
    }

    public function edit(Card $card)
    {
        abort_if(Gate::denies('card_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companies = Company::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $card->load('company');

        return view('admin.cards.edit', compact('card', 'companies'));
    }

    public function update(UpdateCardRequest $request, Card $card)
    {
        $card->update($request->all());

        return redirect()->route('admin.cards.index');
    }

    public function show(Card $card)
    {
        abort_if(Gate::denies('card_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $card->load('company');

        return view('admin.cards.show', compact('card'));
    }

    public function destroy(Card $card)
    {
        abort_if(Gate::denies('card_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $card->delete();

        return back();
    }

    public function massDestroy(MassDestroyCardRequest $request)
    {
        $cards = Card::find(request('ids'));

        foreach ($cards as $card) {
            $card->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    private function buildCardsQuery(): Builder
    {
        $query = Card::with(['company', 'primaryDrivers', 'drivers']);

        if (session()->has('company_id') && (string) session()->get('company_id') !== '0') {
            $query->where('company_id', session()->get('company_id'));
        }

        return $query->select(sprintf('%s.*', (new Card)->table));
    }

    private function formatAssignedDriverNames(Card $card): string
    {
        return $card->primaryDrivers
            ->concat($card->drivers)
            ->filter(fn ($driver) => filled($driver?->name))
            ->unique('id')
            ->pluck('name')
            ->implode(', ');
    }

    private function applyCardGlobalSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $matchingTypes = collect(Card::TYPE_RADIO)
            ->filter(function ($label, $key) use ($search) {
                return str_contains(mb_strtolower($label), mb_strtolower($search))
                    || str_contains(mb_strtolower($key), mb_strtolower($search));
            })
            ->keys()
            ->all();

        $query->where(function (Builder $builder) use ($search, $matchingTypes) {
            $like = '%' . $search . '%';

            $builder
                ->where('cards.id', 'like', $like)
                ->orWhere('cards.type', 'like', $like)
                ->orWhere('cards.code', 'like', $like)
                ->orWhereHas('company', fn (Builder $companyQuery) => $companyQuery->where('name', 'like', $like))
                ->orWhereHas('primaryDrivers', fn (Builder $driverQuery) => $driverQuery->where('name', 'like', $like))
                ->orWhereHas('drivers', fn (Builder $driverQuery) => $driverQuery->where('name', 'like', $like));

            if ($matchingTypes !== []) {
                $builder->orWhereIn('cards.type', $matchingTypes);
            }
        });
    }
}
