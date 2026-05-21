<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverDepositMovement;
use App\Models\TvdeWeek;
use App\Services\DriverDepositPlanningService;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DriverDepositMovementController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('driver_deposit_movement_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $filters = $request->only(['driver_id', 'company_id', 'type', 'tvde_week_id', 'date_from', 'date_to']);

        $movements = DriverDepositMovement::with(['driver', 'company', 'tvde_week', 'creator'])
            ->whereIn('type', array_keys(DriverDepositMovement::REAL_TYPE_SELECT))
            ->when($filters['driver_id'] ?? null, fn ($query, $driverId) => $query->where('driver_id', $driverId))
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['tvde_week_id'] ?? null, fn ($query, $weekId) => $query->where('tvde_week_id', $weekId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        if ($request->input('export')) {
            return $this->exportMovements($movements, $request->input('export'));
        }

        return view('admin.driverDepositMovements.index', array_merge($this->formData(), compact('movements', 'filters')));
    }

    public function create()
    {
        abort_if(Gate::denies('driver_deposit_movement_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.driverDepositMovements.create', $this->formData());
    }

    public function store(Request $request, DriverDepositPlanningService $service)
    {
        abort_if(Gate::denies('driver_deposit_movement_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $service->recordMovement($request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'tvde_week_id' => ['nullable', 'integer', 'exists:tvde_weeks,id'],
            'type' => ['required', 'string', Rule::in(array_keys(DriverDepositMovement::REAL_TYPE_SELECT))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]));

        return redirect()->route('admin.driver-deposit-real-movements.index')->with('message', 'Movimento real registado com sucesso.');
    }

    private function formData(): array
    {
        return [
            'drivers' => Driver::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'tvdeWeeks' => TvdeWeek::orderByDesc('start_date')->get(),
            'types' => DriverDepositMovement::REAL_TYPE_SELECT,
        ];
    }

    private function exportMovements($movements, string $format)
    {
        $extension = $format === 'pdf' ? 'html' : 'csv';
        $filename = 'movimentos-reais-caucoes.' . $extension;
        $headers = [
            'Content-Type' => $format === 'pdf' ? 'text/html; charset=UTF-8' : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        if ($format === 'pdf') {
            return response()->view('admin.driverDepositMovements.export', compact('movements'), 200, $headers);
        }

        return response()->streamDownload(function () use ($movements) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Data', 'Motorista', 'Empresa', 'Semana', 'Tipo', 'Valor', 'Metodo', 'Descricao']);

            foreach ($movements as $movement) {
                fputcsv($out, [
                    optional($movement->created_at)->format('Y-m-d'),
                    $movement->driver->name ?? '',
                    $movement->company->name ?? '',
                    $movement->tvde_week->start_date ?? '',
                    DriverDepositMovement::REAL_TYPE_SELECT[$movement->type] ?? $movement->type,
                    $movement->amount,
                    $movement->payment_method,
                    $movement->description,
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }
}
