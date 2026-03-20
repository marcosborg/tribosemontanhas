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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class DriverController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('driver_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = $this->buildDriversQuery();

            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'driver_show';
                $editGate = 'driver_edit';
                $deleteGate = 'driver_delete';
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
            $table->addColumn('state_name', fn($row) => $row->state?->name ?: '');

            $table->editColumn('payment_vat', fn($row) => $row->payment_vat ?: '');
            $table->editColumn('driver_vat', fn($row) => $row->driver_vat ?: '');
            $table->editColumn('document_type', fn($row) => $row->document_type_label ?: '');
            $table->editColumn('citizen_card', fn($row) => $row->citizen_card ?: '');
            $table->editColumn('phone', fn($row) => $row->phone ?: '');
            $table->editColumn('email', fn($row) => $row->email ?: '');
            $table->editColumn('emergency_contact', fn($row) => $row->emergency_contact ?: '');

            $table->editColumn('uber_uuid', fn($row) => $row->uber_uuid ?: '');
            $table->editColumn('bolt_name', fn($row) => $row->bolt_name ?: '');
            $table->addColumn('company_name', fn($row) => $row->company?->name ?: '');

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

            $table->filter(function (Builder $query) use ($request) {
                $this->applyDriverGlobalSearch($query, (string) $request->input('search.value', ''));
            }, false);

            $table->rawColumns(['actions', 'placeholder', 'user', 'card', 'electric', 'local', 'contract_vat', 'state', 'company']);

            return $table->make(true);
        }

        return view('admin.drivers.index');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_if(Gate::denies('driver_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = $this->buildDriversQuery();
        $this->applyDriverGlobalSearch($query, (string) $request->input('search.value', ''));
        $this->applyDriverColumnSearch($query, (array) $request->input('columns', []));

        $drivers = $query->orderBy('id', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="condutores.csv"',
        ];

        $columns = [
            'ID' => fn (Driver $driver) => $driver->id,
            'Utilizador ID' => fn (Driver $driver) => $driver->user_id,
            'Utilizador' => fn (Driver $driver) => $driver->user?->name,
            'Email utilizador' => fn (Driver $driver) => $driver->user?->email,
            'Codigo interno' => fn (Driver $driver) => $driver->code,
            'Nome' => fn (Driver $driver) => $driver->name,
            'Cartao principal ID' => fn (Driver $driver) => $driver->card_id,
            'Cartao principal' => fn (Driver $driver) => $driver->card?->code,
            'Cartoes' => fn (Driver $driver) => $driver->cards->pluck('code')->implode(', '),
            'Eletrico ID' => fn (Driver $driver) => $driver->electric_id,
            'Eletrico' => fn (Driver $driver) => $driver->electric?->code,
            'Cartao portagens ID' => fn (Driver $driver) => $driver->tool_card_id,
            'Cartao portagens' => fn (Driver $driver) => $driver->tool_card?->code,
            'Local ID' => fn (Driver $driver) => $driver->local_id,
            'Local' => fn (Driver $driver) => $driver->local?->name,
            'IVA contrato ID' => fn (Driver $driver) => $driver->contract_vat_id,
            'IVA contrato' => fn (Driver $driver) => $driver->contract_vat?->name,
            'Data inicio' => fn (Driver $driver) => $driver->getRawOriginal('start_date'),
            'Data fim' => fn (Driver $driver) => $driver->getRawOriginal('end_date'),
            'Motivo saida' => fn (Driver $driver) => $driver->reason,
            'Telefone' => fn (Driver $driver) => $driver->phone,
            'Contacto emergencia' => fn (Driver $driver) => $driver->emergency_contact,
            'NIF pagamento' => fn (Driver $driver) => $driver->payment_vat,
            'Tipo documento' => fn (Driver $driver) => $driver->document_type_label,
            'Documento identificacao' => fn (Driver $driver) => $driver->citizen_card,
            'Email' => fn (Driver $driver) => $driver->email,
            'IBAN' => fn (Driver $driver) => $driver->iban,
            'SWIFT/BIC' => fn (Driver $driver) => $driver->swift,
            'Endereco' => fn (Driver $driver) => $driver->address,
            'Codigo postal' => fn (Driver $driver) => $driver->zip,
            'Localidade' => fn (Driver $driver) => $driver->city,
            'Estado ID' => fn (Driver $driver) => $driver->state_id,
            'Estado' => fn (Driver $driver) => $driver->state?->name,
            'Carta conducao' => fn (Driver $driver) => $driver->driver_license,
            'NIF motorista' => fn (Driver $driver) => $driver->driver_vat,
            'UUID Uber' => fn (Driver $driver) => $driver->uber_uuid,
            'Nome Bolt' => fn (Driver $driver) => $driver->bolt_name,
            'Matricula viatura' => fn (Driver $driver) => $driver->license_plate,
            'Marca viatura' => fn (Driver $driver) => $driver->brand,
            'Modelo viatura' => fn (Driver $driver) => $driver->model,
            'Notas' => fn (Driver $driver) => $driver->notes,
            'Empresa ID' => fn (Driver $driver) => $driver->company_id,
            'Empresa' => fn (Driver $driver) => $driver->company?->name,
            'Criado em' => fn (Driver $driver) => optional($driver->created_at)->format('Y-m-d H:i:s'),
            'Atualizado em' => fn (Driver $driver) => optional($driver->updated_at)->format('Y-m-d H:i:s'),
            'Eliminado em' => fn (Driver $driver) => optional($driver->deleted_at)->format('Y-m-d H:i:s'),
        ];

        return response()->streamDownload(function () use ($drivers, $columns) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_keys($columns), ';');

            foreach ($drivers as $driver) {
                $row = [];
                foreach ($columns as $resolver) {
                    $row[] = $resolver($driver);
                }
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, 'condutores.csv', $headers);
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

        $document_types = Driver::documentTypeOptions();

        return view('admin.drivers.create', compact('cards', 'companies', 'contract_vats', 'document_types', 'electrics', 'tool_cards', 'locals', 'states', 'users'));
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
        $document_types = Driver::documentTypeOptions();

        $driver->load('user', 'card', 'electric', 'local', 'contract_vat', 'state', 'company');

        return view('admin.drivers.edit', compact('cards', 'companies', 'contract_vats', 'document_types', 'driver', 'electrics', 'tool_cards', 'locals', 'states', 'users'));
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

    private function buildDriversQuery(): Builder
    {
        return Driver::with(['user', 'card', 'cards', 'electric', 'tool_card', 'local', 'contract_vat', 'state', 'company'])
            ->select(sprintf('%s.*', (new Driver)->table));
    }

    private function applyDriverGlobalSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $q) use ($search) {
            $like = '%' . $search . '%';
            $matchingDocumentTypes = collect(Driver::documentTypeOptions())
                ->filter(function ($label, $key) use ($search) {
                    return str_contains(mb_strtolower($label), mb_strtolower($search))
                        || str_contains(mb_strtolower($key), mb_strtolower($search));
                })
                ->keys()
                ->all();

            $q->where('drivers.id', 'like', $like)
                ->orWhere('drivers.user_id', 'like', $like)
                ->orWhere('drivers.code', 'like', $like)
                ->orWhere('drivers.name', 'like', $like)
                ->orWhere('drivers.reason', 'like', $like)
                ->orWhere('drivers.phone', 'like', $like)
                ->orWhere('drivers.emergency_contact', 'like', $like)
                ->orWhere('drivers.payment_vat', 'like', $like)
                ->orWhere('drivers.document_type', 'like', $like)
                ->orWhere('drivers.citizen_card', 'like', $like)
                ->orWhere('drivers.email', 'like', $like)
                ->orWhere('drivers.iban', 'like', $like)
                ->orWhere('drivers.swift', 'like', $like)
                ->orWhere('drivers.address', 'like', $like)
                ->orWhere('drivers.zip', 'like', $like)
                ->orWhere('drivers.city', 'like', $like)
                ->orWhere('drivers.driver_license', 'like', $like)
                ->orWhere('drivers.driver_vat', 'like', $like)
                ->orWhere('drivers.uber_uuid', 'like', $like)
                ->orWhere('drivers.bolt_name', 'like', $like)
                ->orWhere('drivers.license_plate', 'like', $like)
                ->orWhere('drivers.brand', 'like', $like)
                ->orWhere('drivers.model', 'like', $like)
                ->orWhere('drivers.notes', 'like', $like)
                ->orWhere('drivers.start_date', 'like', $like)
                ->orWhere('drivers.end_date', 'like', $like)
                ->orWhereHas('user', fn (Builder $qq) => $qq->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->orWhereHas('card', fn (Builder $qq) => $qq->where('code', 'like', $like))
                ->orWhereHas('cards', fn (Builder $qq) => $qq->where('code', 'like', $like))
                ->orWhereHas('electric', fn (Builder $qq) => $qq->where('code', 'like', $like))
                ->orWhereHas('tool_card', fn (Builder $qq) => $qq->where('code', 'like', $like))
                ->orWhereHas('local', fn (Builder $qq) => $qq->where('name', 'like', $like))
                ->orWhereHas('contract_vat', fn (Builder $qq) => $qq->where('name', 'like', $like))
                ->orWhereHas('state', fn (Builder $qq) => $qq->where('name', 'like', $like))
                ->orWhereHas('company', fn (Builder $qq) => $qq->where('name', 'like', $like));

            if (!empty($matchingDocumentTypes)) {
                $q->orWhereIn('drivers.document_type', $matchingDocumentTypes);
            }
        });
    }

    private function applyDriverColumnSearch(Builder $query, array $columns): void
    {
        $map = [
            'drivers.id' => 'drivers.id',
            'user_name' => fn (Builder $q, string $value) => $q->whereHas('user', fn (Builder $qq) => $qq->where('name', 'like', "%{$value}%")),
            'user.email' => fn (Builder $q, string $value) => $q->whereHas('user', fn (Builder $qq) => $qq->where('email', 'like', "%{$value}%")),
            'drivers.code' => 'drivers.code',
            'contract_vat_name' => fn (Builder $q, string $value) => $q->whereHas('contract_vat', fn (Builder $qq) => $qq->where('name', 'like', "%{$value}%")),
            'state_name' => fn (Builder $q, string $value) => $q->whereHas('state', fn (Builder $qq) => $qq->where('name', 'like', "%{$value}%")),
            'drivers.driver_vat' => 'drivers.driver_vat',
            'drivers.uber_uuid' => 'drivers.uber_uuid',
            'drivers.bolt_name' => 'drivers.bolt_name',
        ];

        foreach ($columns as $column) {
            $name = $column['name'] ?? null;
            $value = trim((string) data_get($column, 'search.value', ''));

            if (!$name || $value === '' || !array_key_exists($name, $map)) {
                continue;
            }

            $resolver = $map[$name];

            if (is_string($resolver)) {
                $query->where($resolver, 'like', '%' . $value . '%');
                continue;
            }

            $resolver($query, $value);
        }
    }
}
