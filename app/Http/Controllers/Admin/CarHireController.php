<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyCarHireRequest;
use App\Http\Requests\StoreCarHireRequest;
use App\Http\Requests\UpdateCarHireRequest;
use App\Models\CarHire;
use App\Models\Driver;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class CarHireController extends Controller
{
    use CsvImportTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('car_hire_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = CarHire::with(['driver'])
                ->select(sprintf('%s.*', (new CarHire)->table));

            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'car_hire_show';
                $editGate = 'car_hire_edit';
                $deleteGate = 'car_hire_delete';
                $crudRoutePart = 'car-hires';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', fn($row) => $row->id ?? '');
            $table->editColumn('name', fn($row) => $row->name ?? '');
            $table->editColumn('amount', fn($row) => $row->amount ?? '');
            $table->editColumn('start_date', fn($row) => $row->start_date ?? '');
            $table->editColumn('end_date', fn($row) => $row->end_date ?? '');

            // Relacional
            $table->addColumn('driver_name', fn($row) => $row->driver?->name ?: '');

            // ======= Filtros server-side por coluna =======
            $table->filterColumn('name', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->where('name', 'like', "%{$k}%");
            });

            $table->filterColumn('amount', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->where('amount', 'like', "%{$k}%");
            });

            $table->filterColumn('start_date', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->where('start_date', 'like', "%{$k}%");
            });

            $table->filterColumn('end_date', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->where('end_date', 'like', "%{$k}%");
            });

            $table->filterColumn('driver_name', function ($q, $k) {
                $k = trim($k);
                if ($k === '')
                    return;
                $q->whereHas('driver', fn($qq) => $qq->where('name', 'like', "%{$k}%"));
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.carHires.index');
    }

    public function create()
    {
        abort_if(Gate::denies('car_hire_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $drivers = Driver::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.carHires.create', compact('drivers'));
    }

    public function store(StoreCarHireRequest $request)
    {
        // 1) Criar SEMPRE o novo registo
        $carHire = CarHire::create($request->all());

        // 2) Ler as datas exactamente como foram gravadas na BD
        $startDate = $carHire->getRawOriginal('start_date');
        $endDate = $carHire->getRawOriginal('end_date');

        // 3) Verificar sobreposição com outros registos do MESMO driver
        $hasOverlap = CarHire::where('driver_id', $carHire->driver_id)
            ->where('id', '!=', $carHire->id) // ignora o próprio
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate);
            })
            ->first();

        if ($hasOverlap) {
            return redirect()->route('admin.car-hires.index')
                ->with(
                    'error_message',
                    "Aluguer criado com sucesso (ID {$carHire->id}), mas sobrepõe o aluguer existente com ID {$hasOverlap->id}."
                );
        }

        return redirect()->route('admin.car-hires.index')
            ->with('success', "Aluguer criado com sucesso (ID {$carHire->id}).");
    }


    public function edit(CarHire $carHire)
    {
        abort_if(Gate::denies('car_hire_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $drivers = Driver::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $carHire->load('driver');

        return view('admin.carHires.edit', compact('carHire', 'drivers'));
    }

    public function update(UpdateCarHireRequest $request, CarHire $carHire)
    {
        $carHire->update($request->all());

        return redirect()->route('admin.car-hires.index');
    }

    public function show(CarHire $carHire)
    {
        abort_if(Gate::denies('car_hire_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $carHire->load('driver');

        return view('admin.carHires.show', compact('carHire'));
    }

    public function destroy(CarHire $carHire)
    {
        abort_if(Gate::denies('car_hire_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $carHire->delete();

        return back();
    }

    public function massDestroy(MassDestroyCarHireRequest $request)
    {
        $carHires = CarHire::find(request('ids'));

        foreach ($carHires as $carHire) {
            $carHire->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
