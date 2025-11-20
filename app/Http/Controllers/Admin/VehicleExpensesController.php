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
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'vehicle_expense_show';
                $editGate = 'vehicle_expense_edit';
                $deleteGate = 'vehicle_expense_delete';
                $crudRoutePart = 'vehicle-expenses';

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

            $table->rawColumns(['actions', 'placeholder', 'vehicle_item', 'files']);

            return $table->make(true);
        }

        $vehicle_items = VehicleItem::get();

        return view('admin.vehicleExpenses.index', compact('vehicle_items'));
    }

    public function create()
    {
        abort_if(Gate::denies('vehicle_expense_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicle_items = VehicleItem::pluck('license_plate', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.vehicleExpenses.create', compact('vehicle_items'));
    }

    public function store(StoreVehicleExpenseRequest $request)
    {
        $vehicleExpense = VehicleExpense::create($request->all());

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
        $vehicleExpense->update($request->all());

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
