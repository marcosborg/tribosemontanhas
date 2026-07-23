<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyWeeklyVehicleExpenseRequest;
use App\Http\Requests\StoreWeeklyVehicleExpenseRequest;
use App\Http\Requests\UpdateWeeklyVehicleExpenseRequest;
use App\Models\Driver;
use App\Models\TvdeWeek;
use App\Models\VehicleItem;
use App\Models\WeeklyVehicleExpense;
use App\Services\WeeklyMileageImporter;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class WeeklyVehicleExpensesController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('weekly_vehicle_expense_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = WeeklyVehicleExpense::with(['vehicle_item', 'driver', 'tvde_week', 'allocations.driver'])
                ->when($request->input('tvde_week_id'), fn ($query, $weekId) => $query->where('tvde_week_id', $weekId))
                ->select(sprintf('%s.*', (new WeeklyVehicleExpense)->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'weekly_vehicle_expense_show';
                $editGate      = 'weekly_vehicle_expense_edit';
                $deleteGate    = 'weekly_vehicle_expense_delete';
                $crudRoutePart = 'weekly-vehicle-expenses';

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

            $table->addColumn('driver_name', function ($row) {
                return $row->driver ? $row->driver->name : '';
            });

            $table->addColumn('tvde_week_start_date', function ($row) {
                return $row->tvde_week ? $row->tvde_week->start_date : '';
            });

            $table->editColumn('total_km', function ($row) {
                return $row->odometer_end !== null ? number_format((float) $row->odometer_end, 2, ',', ' ') : '';
            });
            $table->editColumn('weekly_km', function ($row) {
                return $row->distance_km !== null ? number_format((float) $row->distance_km, 2, ',', ' ') : '';
            });
            $table->editColumn('extra_km', function ($row) {
                return $row->allocations->whereNotNull('extra_km')->isNotEmpty()
                    ? number_format((float) $row->allocations->sum('extra_km'), 2, ',', ' ')
                    : '';
            });
            $table->addColumn('drivers', fn ($row) => $row->allocations->map(fn ($allocation) => e(optional($allocation->driver)->name) . ' (' . number_format((float) $allocation->allocated_km, 2, ',', ' ') . ' km)')->implode('<br>'));
            $table->editColumn('source', fn ($row) => $row->source === WeeklyVehicleExpense::SOURCE_TESLA ? 'Tesla' : 'CarTrack');
            $table->editColumn('status', fn ($row) => match ($row->status) {
                WeeklyVehicleExpense::STATUS_READY => '<span class="label label-success">Validado</span>',
                WeeklyVehicleExpense::STATUS_BASELINE => '<span class="label label-info">Leitura base</span>',
                default => '<span class="label label-warning">Revisão</span>' . ($row->status_reason ? '<br><small>' . e($row->status_reason) . '</small>' : ''),
            });
            $table->addColumn('review', fn ($row) => $row->distance_km !== null && Gate::allows('weekly_vehicle_expense_edit')
                ? '<button type="button" class="btn btn-xs btn-info review-mileage" data-id="' . $row->id . '" data-distance="' . $row->distance_km . '">Alocar</button>'
                : '');

            $table->rawColumns(['actions', 'placeholder', 'vehicle_item', 'driver', 'tvde_week', 'drivers', 'status', 'review']);

            return $table->make(true);
        }

        $weeks = TvdeWeek::orderByDesc('start_date')->get();
        $selectedWeekId = (int) old('tvde_week_id', request('tvde_week_id', session('tvde_week_id', optional($weeks->first())->id)));
        $drivers = Driver::orderBy('name')->pluck('name', 'id');

        return view('admin.weeklyVehicleExpenses.index', compact('weeks', 'selectedWeekId', 'drivers'));
    }

    public function importTesla(Request $request, WeeklyMileageImporter $importer)
    {
        return $this->importMileage($request, $importer, WeeklyVehicleExpense::SOURCE_TESLA);
    }

    public function importCarTrack(Request $request, WeeklyMileageImporter $importer)
    {
        return $this->importMileage($request, $importer, WeeklyVehicleExpense::SOURCE_CARTRACK);
    }

    private function importMileage(Request $request, WeeklyMileageImporter $importer, string $source)
    {
        abort_if(Gate::denies('weekly_vehicle_expense_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $extensions = $source === WeeklyVehicleExpense::SOURCE_TESLA ? ['csv', 'txt'] : ['xls', 'xlsx'];
        $request->validate([
            'tvde_week_id' => ['required', 'integer', 'exists:tvde_weeks,id'],
            'mileage_file' => ['required', 'file', function ($attribute, $file, $fail) use ($extensions) {
                if (!in_array(strtolower($file->getClientOriginalExtension()), $extensions, true)) {
                    $fail('Formato de ficheiro inválido.');
                }
            }],
        ]);

        try {
            $file = $request->file('mileage_file');
            $result = $source === WeeklyVehicleExpense::SOURCE_TESLA
                ? $importer->importTesla($file->getRealPath(), $file->getClientOriginalName(), (int) $request->input('tvde_week_id'))
                : $importer->importCarTrack($file->getRealPath(), $file->getClientOriginalName(), (int) $request->input('tvde_week_id'));
            session()->put('tvde_week_id', (int) $request->input('tvde_week_id'));

            return redirect()->route('admin.weekly-vehicle-expenses.index', ['tvde_week_id' => $request->input('tvde_week_id')])
                ->with('message', 'Importação de quilómetros concluída.')
                ->with('weeklyMileageImportReport', $result);
        } catch (\Throwable $exception) {
            return redirect()->route('admin.weekly-vehicle-expenses.index', ['tvde_week_id' => $request->input('tvde_week_id')])
                ->withErrors(['mileage_file' => $exception->getMessage()]);
        }
    }

    public function allocations(WeeklyVehicleExpense $weeklyVehicleExpense)
    {
        abort_if(Gate::denies('weekly_vehicle_expense_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return response()->json([
            'distance_km' => $weeklyVehicleExpense->distance_km,
            'allocations' => $weeklyVehicleExpense->allocations()->get(['driver_id', 'allocated_km']),
        ]);
    }

    public function updateAllocations(Request $request, WeeklyVehicleExpense $weeklyVehicleExpense, WeeklyMileageImporter $importer)
    {
        abort_if(Gate::denies('weekly_vehicle_expense_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $data = $request->validate([
            'driver_ids' => ['required', 'array', 'min:1'],
            'driver_ids.*' => ['required', 'integer', 'distinct', 'exists:drivers,id'],
            'allocated_kms' => ['required', 'array', 'size:' . count($request->input('driver_ids', []))],
            'allocated_kms.*' => ['required', 'numeric', 'min:0.01'],
        ]);
        $allocations = collect($data['driver_ids'])->map(fn ($driverId, $index) => [
            'driver_id' => $driverId,
            'allocated_km' => $data['allocated_kms'][$index],
        ]);
        $allocated = $allocations->sum(fn ($row) => (float) $row['allocated_km']);
        if (abs($allocated - (float) $weeklyVehicleExpense->distance_km) >= 0.01) {
            return back()->withErrors(['allocations' => 'A soma dos quilómetros atribuídos deve ser igual aos quilómetros semanais da viatura.']);
        }

        DB::transaction(function () use ($weeklyVehicleExpense, $allocations, $importer): void {
            $weeklyVehicleExpense->allocations()->delete();
            foreach ($allocations as $row) {
                $driver = Driver::findOrFail($row['driver_id']);
                $allowance = $driver->weekly_km_allowance;
                $weeklyVehicleExpense->allocations()->create([
                    'driver_id' => $driver->id,
                    'allocated_km' => $row['allocated_km'],
                    'allowance_km' => $allowance,
                    'extra_km' => $allowance === null ? null : max(0, (float) $row['allocated_km'] - (float) $allowance),
                    'is_manual' => true,
                ]);
            }
            $importer->refreshStatus($weeklyVehicleExpense);
        });

        return redirect()->route('admin.weekly-vehicle-expenses.index', ['tvde_week_id' => $weeklyVehicleExpense->tvde_week_id])
            ->with('message', 'Alocação de quilómetros atualizada.');
    }

    public function create()
    {
        abort_if(Gate::denies('weekly_vehicle_expense_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicle_items = VehicleItem::pluck('license_plate', 'id')->prepend(trans('global.pleaseSelect'), '');

        $drivers = Driver::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tvde_weeks = TvdeWeek::pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.weeklyVehicleExpenses.create', compact('drivers', 'tvde_weeks', 'vehicle_items'));
    }

    public function store(StoreWeeklyVehicleExpenseRequest $request)
    {
        $weeklyVehicleExpense = WeeklyVehicleExpense::create($request->all());

        return redirect()->route('admin.weekly-vehicle-expenses.index');
    }

    public function edit(WeeklyVehicleExpense $weeklyVehicleExpense)
    {
        abort_if(Gate::denies('weekly_vehicle_expense_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicle_items = VehicleItem::pluck('license_plate', 'id')->prepend(trans('global.pleaseSelect'), '');

        $drivers = Driver::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tvde_weeks = TvdeWeek::pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        $weeklyVehicleExpense->load('vehicle_item', 'driver', 'tvde_week');

        return view('admin.weeklyVehicleExpenses.edit', compact('drivers', 'tvde_weeks', 'vehicle_items', 'weeklyVehicleExpense'));
    }

    public function update(UpdateWeeklyVehicleExpenseRequest $request, WeeklyVehicleExpense $weeklyVehicleExpense)
    {
        $weeklyVehicleExpense->update($request->all());

        return redirect()->route('admin.weekly-vehicle-expenses.index');
    }

    public function show(WeeklyVehicleExpense $weeklyVehicleExpense)
    {
        abort_if(Gate::denies('weekly_vehicle_expense_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $weeklyVehicleExpense->load('vehicle_item', 'driver', 'tvde_week');

        return view('admin.weeklyVehicleExpenses.show', compact('weeklyVehicleExpense'));
    }

    public function destroy(WeeklyVehicleExpense $weeklyVehicleExpense)
    {
        abort_if(Gate::denies('weekly_vehicle_expense_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $weeklyVehicleExpense->delete();

        return back();
    }

    public function massDestroy(MassDestroyWeeklyVehicleExpenseRequest $request)
    {
        $weeklyVehicleExpenses = WeeklyVehicleExpense::find(request('ids'));

        foreach ($weeklyVehicleExpenses as $weeklyVehicleExpense) {
            $weeklyVehicleExpense->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
