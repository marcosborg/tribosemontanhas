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
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class VehicleExpensesController extends Controller
{
    use MediaUploadingTrait, CsvImportTrait {
        processCsvImport as baseProcessCsvImport;
    }

    public function index(Request $request)
    {
        abort_if(Gate::denies('vehicle_expense_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = VehicleExpense::with(['vehicle_item'])->select(sprintf('%s.*', (new VehicleExpense)->table));

            if ($request->filled('date_from')) {
                $query->whereDate('date', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('date', '<=', $request->input('date_to'));
            }

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
            $table->addColumn('paid_status', function ($row) {
                return $row->is_paid ? 'Pago' : 'Por pagar';
            });
            $table->addColumn('paid_at', function ($row) {
                return $row->paid_at ? $row->paid_at->format('Y-m-d H:i:s') : '';
            });
            $table->editColumn('payment_reference', function ($row) {
                return $row->payment_reference ?: '';
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

    public function create()
    {
        abort_if(Gate::denies('vehicle_expense_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicle_items = VehicleItem::pluck('license_plate', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.vehicleExpenses.create', compact('vehicle_items'));
    }

    public function store(StoreVehicleExpenseRequest $request)
    {
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

        return view('admin.vehicleExpenses.show', compact('vehicleExpense'));
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
}
