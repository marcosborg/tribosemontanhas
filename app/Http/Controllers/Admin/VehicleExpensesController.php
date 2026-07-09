<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyVehicleExpenseRequest;
use App\Http\Requests\StoreVehicleExpenseRequest;
use App\Http\Requests\UpdateVehicleExpenseRequest;
use App\Models\VehicleExpense;
use App\Models\VehicleItem;
use App\Services\AccountingVehicleExpenseImporter;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class VehicleExpensesController extends Controller
{
    use MediaUploadingTrait, CsvImportTrait {
        processCsvImport as baseProcessCsvImport;
    }

    public function index(Request $request)
    {
        abort_if(Gate::denies('vehicle_expense_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($request);
        }

        if ($request->ajax()) {
            $query = $this->baseIndexQuery($request);

            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->addColumn('vehicle_item_license_plate', function ($row) {
                return $row->vehicle_item ? $row->vehicle_item->license_plate : '';
            });

            $table->editColumn('expense_type', function ($row) {
                $map = VehicleExpense::EXPENSE_TYPE_RADIO ?? [];
                $val = $row->expense_type;
                return $val !== null ? ($map[$val] ?? $val) : '';
            });

            $table->editColumn('files', function ($row) {
                if (!$row->files) {
                    return '';
                }
                $links = [];
                foreach ($row->files as $media) {
                    $links[] = '<a href="' . $media->getUrl() . '" target="_blank">' . trans('global.downloadFile') . '</a>';
                }

                return implode(', ', $links);
            });
            $table->editColumn('value', function ($row) {
                return $row->value ? $row->value : '';
            });
            $table->editColumn('vat', function ($row) {
                return $row->vat ? $row->vat : '';
            });
            $table->addColumn('final_value', function ($row) {
                $value = (float) ($row->value ?? 0);
                $vat = (float) ($row->vat ?? 0);

                return number_format($value + ($value * ($vat / 100)), 2, '.', '');
            });
            $table->addColumn('group_info', function ($row) {
                if (!$row->group_uuid) {
                    return '';
                }

                return $row->group_label ?: 'Grupo';
            });
            $table->addColumn('paid_status', function ($row) {
                return $row->is_paid ? 'Pago' : 'Por pagar';
            });
            $table->addColumn('paid_at', function ($row) {
                return $row->paid_at ? $row->paid_at->format('Y-m-d H:i:s') : '';
            });
            $table->editColumn('pay_to', function ($row) {
                return $row->pay_to ?: '';
            });
            $table->filterColumn('is_paid', function ($query, $keyword) {
                if ($keyword === '1' || $keyword === '0') {
                    $query->where('is_paid', (bool) $keyword);
                }
            });
            $table->editColumn('actions', function ($row) {
                $viewGate = 'vehicle_expense_show';
                $editGate = 'vehicle_expense_edit';
                $deleteGate = 'vehicle_expense_delete';
                $crudRoutePart = 'vehicle-expenses';

                $actions = view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ))->render();

                if (!$row->is_paid && Gate::allows('vehicle_expense_edit')) {
                    $actions .= sprintf(
                        ' <form action="%s" method="POST" onsubmit="return confirm(\'%s\');" style="display: inline-block;">%s<input type="submit" class="btn btn-xs btn-success" value="Marcar como pago"></form>',
                        route('admin.vehicle-expenses.mark-paid', $row->id),
                        e(trans('global.areYouSure')),
                        csrf_field()
                    );
                }

                return $actions;
            });

            $table->rawColumns(['actions', 'placeholder', 'files']);

            return $table->make(true);
        }

        $vehicle_items = VehicleItem::get();
        $unpaidCount = VehicleExpense::where('is_paid', false)->count();

        return view('admin.vehicleExpenses.index', compact('vehicle_items', 'unpaidCount'));
    }

    protected function baseIndexQuery(Request $request)
    {
        $query = VehicleExpense::with(['vehicle_item'])->select(sprintf('%s.*', (new VehicleExpense)->table));

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        return $query;
    }

    protected function exportCsv(Request $request): StreamedResponse
    {
        $query = $this->baseIndexQuery($request);
        $this->applyExportFilters($query, $request);
        $this->applyExportOrder($query, $request);

        $filename = 'despesas-viaturas-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Viatura',
                'Tipo de despesa',
                'Data',
                'Estado',
                'Pago em',
                'Ficheiros',
                'Valor',
                'IVA',
                'Valor final',
                'Grupo',
                'Pagar a',
            ]);

            $query->chunk(500, function ($expenses) use ($handle) {
                foreach ($expenses as $expense) {
                    $value = (float) ($expense->value ?? 0);
                    $vat = (float) ($expense->vat ?? 0);

                    fputcsv($handle, [
                        $expense->id,
                        optional($expense->vehicle_item)->license_plate,
                        VehicleExpense::EXPENSE_TYPE_RADIO[$expense->expense_type] ?? $expense->expense_type,
                        $expense->getRawOriginal('date'),
                        $expense->is_paid ? 'Pago' : 'Por pagar',
                        $expense->paid_at ? $expense->paid_at->format('Y-m-d H:i:s') : '',
                        $expense->files->map(fn ($media) => $media->getUrl())->implode(' | '),
                        $expense->value,
                        $expense->vat,
                        number_format($value + ($value * ($vat / 100)), 2, '.', ''),
                        $expense->group_label ?: ($expense->group_uuid ? 'Grupo' : ''),
                        $expense->pay_to,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function applyExportFilters($query, Request $request): void
    {
        $columns = $request->input('columns', []);

        $this->applyTextFilter($query, $columns, 1, 'id');
        $this->applyVehicleFilter($query, $columns);
        $this->applyExactFilter($query, $columns, 3, 'expense_type');
        $this->applyPaidFilter($query, $columns);
        $this->applyTextFilter($query, $columns, 8, 'value');
        $this->applyTextFilter($query, $columns, 9, 'vat');
        $this->applyTextFilter($query, $columns, 11, 'group_label');
        $this->applyTextFilter($query, $columns, 12, 'pay_to');

        $global = trim((string) data_get($request->input('search', []), 'value', ''));
        if ($global === '') {
            return;
        }

        $query->where(function ($query) use ($global) {
            $query->where('id', 'like', "%{$global}%")
                ->orWhere('expense_type', 'like', "%{$global}%")
                ->orWhereDate('date', $global)
                ->orWhere('value', 'like', "%{$global}%")
                ->orWhere('vat', 'like', "%{$global}%")
                ->orWhere('group_label', 'like', "%{$global}%")
                ->orWhere('pay_to', 'like', "%{$global}%")
                ->orWhereHas('vehicle_item', function ($query) use ($global) {
                    $query->where('license_plate', 'like', "%{$global}%");
                });
        });
    }

    protected function applyExportOrder($query, Request $request): void
    {
        $order = $request->input('order.0');
        $column = (int) data_get($order, 'column', 1);
        $direction = data_get($order, 'dir') === 'asc' ? 'asc' : 'desc';

        $map = [
            1 => 'id',
            3 => 'expense_type',
            4 => 'date',
            5 => 'is_paid',
            6 => 'paid_at',
            8 => 'value',
            9 => 'vat',
            11 => 'group_label',
            12 => 'pay_to',
        ];

        $query->orderBy($map[$column] ?? 'id', $direction);
    }

    protected function applyTextFilter($query, array $columns, int $index, string $field): void
    {
        $value = $this->columnSearchValue($columns, $index);
        if ($value !== '') {
            $query->where($field, 'like', "%{$value}%");
        }
    }

    protected function applyExactFilter($query, array $columns, int $index, string $field): void
    {
        $value = $this->columnSearchValue($columns, $index);
        if ($value !== '') {
            $query->where($field, $value);
        }
    }

    protected function applyVehicleFilter($query, array $columns): void
    {
        $value = $this->columnSearchValue($columns, 2);
        if ($value !== '') {
            $query->whereHas('vehicle_item', function ($query) use ($value) {
                $query->where('license_plate', 'like', "%{$value}%");
            });
        }
    }

    protected function applyPaidFilter($query, array $columns): void
    {
        $value = $this->columnSearchValue($columns, 5);
        if ($value === '1' || $value === '0') {
            $query->where('is_paid', (bool) $value);
        }
    }

    protected function columnSearchValue(array $columns, int $index): string
    {
        $value = trim((string) data_get($columns, "{$index}.search.value", ''));

        return preg_replace('/^\^(.+)\$$/', '$1', $value);
    }

    public function create()
    {
        abort_if(Gate::denies('vehicle_expense_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicle_items = VehicleItem::pluck('license_plate', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.vehicleExpenses.create', compact('vehicle_items'));
    }

    public function store(StoreVehicleExpenseRequest $request)
    {
        if ($request->boolean('is_group_expense')) {
            return $this->storeGroup($request);
        }

        $payload = $request->all();
        $payload['is_paid'] = $request->boolean('is_paid');
        $payload['paid_at'] = $request->boolean('is_paid') ? now() : null;

        $vehicleExpense = VehicleExpense::create($payload);

        foreach ($request->input('files', []) as $file) {
            $vehicleExpense->addMedia(storage_path('tmp/uploads/' . basename($file)))->toMediaCollection('files');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $vehicleExpense->id]);
        }

        return redirect()->route('admin.vehicle-expenses.index');
    }

    public function edit(VehicleExpense $vehicleExpense)
    {
        abort_if(Gate::denies('vehicle_expense_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicle_items = VehicleItem::pluck('license_plate', 'id')->prepend(trans('global.pleaseSelect'), '');

        $vehicleExpense->load('vehicle_item');

        return view('admin.vehicleExpenses.edit', compact('vehicleExpense', 'vehicle_items'));
    }

    public function update(UpdateVehicleExpenseRequest $request, VehicleExpense $vehicleExpense)
    {
        $payload = $request->all();
        $wasPaid = (bool) $vehicleExpense->is_paid;
        $isPaid = $request->boolean('is_paid');

        $payload['is_paid'] = $isPaid;
        $payload['paid_at'] = $isPaid
            ? ($wasPaid ? $vehicleExpense->paid_at : now())
            : null;

        $vehicleExpense->update($payload);

        if (count($vehicleExpense->files) > 0) {
            foreach ($vehicleExpense->files as $media) {
                if (!in_array($media->file_name, $request->input('files', []))) {
                    $media->delete();
                }
            }
        }
        $media = $vehicleExpense->files->pluck('file_name')->toArray();
        foreach ($request->input('files', []) as $file) {
            if (count($media) === 0 || !in_array($file, $media)) {
                $vehicleExpense->addMedia(storage_path('tmp/uploads/' . basename($file)))->toMediaCollection('files');
            }
        }

        return redirect()->route('admin.vehicle-expenses.index');
    }

    public function show(VehicleExpense $vehicleExpense)
    {
        abort_if(Gate::denies('vehicle_expense_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicleExpense->load('vehicle_item');

        $groupExpenses = collect();
        if ($vehicleExpense->group_uuid) {
            $groupExpenses = VehicleExpense::with('vehicle_item')
                ->where('group_uuid', $vehicleExpense->group_uuid)
                ->where('id', '<>', $vehicleExpense->id)
                ->orderBy('id')
                ->get();
        }

        return view('admin.vehicleExpenses.show', compact('vehicleExpense', 'groupExpenses'));
    }

    public function markPaid(VehicleExpense $vehicleExpense)
    {
        abort_if(Gate::denies('vehicle_expense_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicleExpense->update([
            'is_paid' => true,
            'paid_at' => now(),
        ]);

        return redirect()->route('admin.vehicle-expenses.index');
    }

    public function importAccounting(Request $request, AccountingVehicleExpenseImporter $importer)
    {
        abort_if(Gate::denies('vehicle_expense_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'accounting_file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $extension = strtolower((string) $value->getClientOriginalExtension());

                    if (!in_array($extension, ['csv', 'txt', 'xls', 'xlsx'], true)) {
                        $fail('O ficheiro deve ser CSV, TXT, XLS ou XLSX.');
                    }
                },
            ],
        ]);

        try {
            $file = $request->file('accounting_file');
            $result = $importer->import($file->getRealPath(), $file->getClientOriginalName());

            return redirect()->route('admin.vehicle-expenses.index')
                ->with('message', 'Importacao concluida: ' . $result['imported'] . ' despesas importadas.')
                ->with('vehicleExpenseImportReport', $result);
        } catch (\Throwable $exception) {
            return redirect()->route('admin.vehicle-expenses.index')
                ->withErrors(['accounting_file' => $exception->getMessage()]);
        }
    }

    public function destroy(VehicleExpense $vehicleExpense)
    {
        abort_if(Gate::denies('vehicle_expense_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicleExpense->delete();

        return back();
    }

    public function massDestroy(MassDestroyVehicleExpenseRequest $request)
    {
        $vehicleExpenses = VehicleExpense::find(request('ids'));

        foreach ($vehicleExpenses as $vehicleExpense) {
            $vehicleExpense->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('vehicle_expense_create') && Gate::denies('vehicle_expense_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model = new VehicleExpense();
        $model->id = $request->input('crud_id', 0);
        $model->exists = true;
        $media = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }

    public function processCsvImport(Request $request)
    {
        // Apenas alteramos o CSV import para VehicleExpense; os restantes modelos usam o comportamento base
        if ($request->input('modelName') !== 'VehicleExpense') {
            return $this->baseProcessCsvImport($request);
        }

        try {
            $filename = $request->input('filename');
            $path     = storage_path('app/csv_import/' . $filename);

            $hasHeader = $request->boolean('hasHeader');

            $fields = array_flip(array_filter($request->input('fields', [])));

            $reader = new \SpreadsheetReader($path);
            $insert = [];

            foreach ($reader as $key => $row) {
                if ($hasHeader && $key === 0) {
                    continue;
                }

                $tmp = [];
                foreach ($fields as $header => $k) {
                    if (isset($row[$k])) {
                        $tmp[$header] = $row[$k];
                    }
                }

                if (array_key_exists('vehicle_item_id', $tmp)) {
                    $tmp['vehicle_item_id'] = $this->resolveVehicleItemId($tmp['vehicle_item_id']);
                }

                if (count($tmp) > 0) {
                    $insert[] = $tmp;
                }
            }

            foreach (array_chunk($insert, 100) as $insert_item) {
                VehicleExpense::insert($insert_item);
            }

            $rows  = count($insert);
            $table = Str::plural('VehicleExpense');

            File::delete($path);

            session()->flash('message', trans('global.app_imported_rows_to_table', ['rows' => $rows, 'table' => $table]));

            return redirect($request->input('redirect'));
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    protected function resolveVehicleItemId($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Se já for um ID numérico, devolvemos tal como vem
        if (is_numeric($value)) {
            return $value;
        }

        $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($value));
        if ($normalized === '') {
            return null;
        }

        return VehicleItem::whereRaw("REPLACE(REPLACE(UPPER(license_plate), ' ', ''), '-', '') = ?", [$normalized])
            ->value('id');
    }

    protected function storeGroup(StoreVehicleExpenseRequest $request)
    {
        $vehicleIds = array_values(array_unique(array_map('intval', $request->input('vehicle_item_ids', []))));
        $vehicleValues = $request->input('vehicle_values', []);

        $validator = Validator::make([
            'vehicle_item_ids' => $vehicleIds,
            'vehicle_values' => $vehicleValues,
        ], [
            'vehicle_item_ids' => ['required', 'array', 'min:2'],
            'vehicle_item_ids.*' => ['integer', 'exists:vehicle_items,id', 'distinct'],
        ]);

        $validator->after(function ($validator) use ($vehicleIds, $vehicleValues) {
            foreach ($vehicleIds as $vehicleId) {
                $value = $vehicleValues[$vehicleId] ?? null;

                if ($value === null || $value === '') {
                    $validator->errors()->add("vehicle_values.$vehicleId", 'Indique o valor para cada viatura selecionada.');
                    continue;
                }

                if (!is_numeric($value) || (float) $value < 0) {
                    $validator->errors()->add("vehicle_values.$vehicleId", 'O valor de cada viatura deve ser numerico e maior ou igual a zero.');
                }
            }
        });

        $validator->validate();

        $commonPayload = $request->except([
            'vehicle_item_id',
            'vehicle_item_ids',
            'vehicle_values',
            'is_group_expense',
            'files',
            'ck-media',
        ]);
        $commonPayload['is_paid'] = $request->boolean('is_paid');
        $commonPayload['paid_at'] = $request->boolean('is_paid') ? now() : null;
        $commonPayload['group_uuid'] = (string) Str::uuid();

        $createdExpenses = collect();

        foreach ($vehicleIds as $vehicleId) {
            $payload = $commonPayload;
            $payload['vehicle_item_id'] = $vehicleId;
            $payload['value'] = $vehicleValues[$vehicleId];

            $vehicleExpense = VehicleExpense::create($payload);
            $this->attachFilesToExpense($vehicleExpense, $request->input('files', []));
            $createdExpenses->push($vehicleExpense);
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $createdExpenses->first()->id]);
        }

        return redirect()->route('admin.vehicle-expenses.index')
            ->with('message', 'Despesa em grupo criada com ' . $createdExpenses->count() . ' despesas.');
    }

    protected function attachFilesToExpense(VehicleExpense $vehicleExpense, array $files): void
    {
        foreach ($files as $file) {
            $source = storage_path('tmp/uploads/' . basename($file));
            if (!File::exists($source)) {
                continue;
            }

            $copyName = uniqid() . '_' . basename($file);
            $copyPath = storage_path('tmp/uploads/' . $copyName);
            File::copy($source, $copyPath);

            $vehicleExpense->addMedia($copyPath)
                ->usingFileName(basename($file))
                ->toMediaCollection('files');
        }
    }
}
