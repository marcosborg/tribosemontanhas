<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyDriverRequest;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Models\Card;
use App\Models\Company;
use App\Models\ContractType;
use App\Models\ContractVat;
use App\Models\Driver;
use App\Models\Electric;
use App\Models\Local;
use App\Models\State;
use App\Models\TollCard;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class DriverController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('driver_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = Driver::with(['user', 'card', 'cards', 'electric', 'tool_card', 'local', 'contract_vat', 'state', 'company'])
                ->select(sprintf('%s.*', (new Driver)->table));

            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'driver_show';
                $editGate      = 'driver_edit';
                $deleteGate    = 'driver_delete';
                $crudRoutePart = 'drivers';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', fn($row) => $row->id ?: '');

            // Colunas derivadas / relações
            $table->addColumn('user_name', fn($row) => $row->user?->name ?: '');
            $table->editColumn('user.email', fn($row) => $row->user ? (is_string($row->user) ? $row->user : $row->user->email) : '');
            $table->editColumn('code', fn($row) => $row->code ?: '');

            $table->addColumn('contract_vat_name', fn($row) => $row->contract_vat?->name ?: '');
            $table->addColumn('state_name',        fn($row) => $row->state?->name ?: '');
            $table->addColumn('company_name',      fn($row) => $row->company?->name ?: '');

            $table->editColumn('uber_uuid',  fn($row) => $row->uber_uuid ?: '');
            $table->editColumn('bolt_name',  fn($row) => $row->bolt_name ?: '');

            // === Filtros por coluna (relações) ===
            $table->filterColumn('user_name', function ($q, $k) {
                $q->whereHas('user', fn($qq) => $qq->where('name', 'like', "%{$k}%"));
            });
            $table->filterColumn('user.email', function ($q, $k) {
                $q->whereHas('user', fn($qq) => $qq->where('email', 'like', "%{$k}%"));
            });
            $table->filterColumn('contract_vat_name', function ($q, $k) {
                $q->whereHas('contract_vat', fn($qq) => $qq->where('name', 'like', "%{$k}%"));
            });
            $table->filterColumn('state_name', function ($q, $k) {
                $q->whereHas('state', fn($qq) => $qq->where('name', 'like', "%{$k}%"));
            });
            $table->filterColumn('company_name', function ($q, $k) {
                $q->whereHas('company', fn($qq) => $qq->where('name', 'like', "%{$k}%"));
            });

            $table->rawColumns(['actions', 'placeholder', 'user', 'card', 'electric', 'local', 'contract_vat', 'state', 'company']);

            return $table->make(true);
        }

        return view('admin.drivers.index');
    }

    public function create()
    {
        abort_if(Gate::denies('driver_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cards = Card::pluck('code', 'id')->prepend(trans('global.pleaseSelect'), '');

        $electrics = Electric::pluck('code', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tool_cards = TollCard::pluck('code', 'id')->prepend(trans('global.pleaseSelect'), '');

        $locals = Local::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $contract_vats = ContractVat::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $states = State::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $companies = Company::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.drivers.create', compact('cards', 'companies', 'contract_vats', 'electrics', 'tool_cards', 'locals', 'states', 'users'));
    }

    public function store(StoreDriverRequest $request)
    {
        $driver = Driver::create($request->all());
        $driver->cards()->sync($request->input('cards', []));

        return redirect()->route('admin.drivers.index');
    }

    public function edit(Driver $driver)
    {
        abort_if(Gate::denies('driver_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cards = Card::pluck('code', 'id')->prepend(trans('global.pleaseSelect'), '');

        $electrics = Electric::pluck('code', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tool_cards = TollCard::pluck('code', 'id')->prepend(trans('global.pleaseSelect'), '');

        $locals = Local::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $contract_vats = ContractVat::all();

        $states = State::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $companies = Company::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $driver->load('user', 'card', 'electric', 'local', 'contract_vat', 'state', 'company');

        return view('admin.drivers.edit', compact('cards', 'companies', 'contract_vats', 'driver', 'electrics', 'tool_cards', 'locals', 'states', 'users'));
    }

    public function update(UpdateDriverRequest $request, Driver $driver)
    {
        $driver->update($request->all());
        $driver->cards()->sync($request->input('cards', []));

        return redirect()->route('admin.drivers.index');
    }

    public function show(Driver $driver)
    {
        abort_if(Gate::denies('driver_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $driver->load('user', 'card', 'electric', 'tool_card', 'local', 'contract_vat', 'state', 'company', 'driverDocuments', 'driverReceipts');

        return view('admin.drivers.show', compact('driver'));
    }

    public function destroy(Driver $driver)
    {
        abort_if(Gate::denies('driver_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $driver->delete();

        return back();
    }

    public function massDestroy(MassDestroyDriverRequest $request)
    {
        $drivers = Driver::find(request('ids'));

        foreach ($drivers as $driver) {
            $driver->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
