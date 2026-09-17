<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\VehicleItem;
use App\Models\VehicleMaintenanceRecord;
use App\Models\VehicleMaintenanceSchedule;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class VehicleMaintenanceController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('vehicle_item_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companyId = $request->input('company_id');
        $vehicleItems = VehicleItem::with(['company', 'maintenance_schedules.records'])
            ->withMax('weekly_vehicle_expenses as current_km', 'odometer_end')
            ->when($companyId, fn ($query, $id) => $query->where('company_id', $id))
            ->orderBy('license_plate')
            ->get();
        $companies = Company::orderBy('name')->get();
        $types = VehicleMaintenanceSchedule::TYPE_SELECT;

        return view('admin.vehicleMaintenances.index', compact('vehicleItems', 'companies', 'companyId', 'types'));
    }

    public function edit(VehicleItem $vehicleItem)
    {
        abort_if(Gate::denies('vehicle_item_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicleItem->load('maintenance_schedules');
        $schedules = $vehicleItem->maintenance_schedules->keyBy('type');
        $types = VehicleMaintenanceSchedule::TYPE_SELECT;

        return view('admin.vehicleMaintenances.edit', compact('vehicleItem', 'schedules', 'types'));
    }

    public function update(Request $request, VehicleItem $vehicleItem)
    {
        abort_if(Gate::denies('vehicle_item_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'schedules' => ['required', 'array'],
            'schedules.*.enabled' => ['nullable', 'boolean'],
            'schedules.*.interval_km' => ['nullable', 'integer', 'min:1'],
            'schedules.*.last_service_km' => ['nullable', 'integer', 'min:0'],
            'schedules.*.last_service_date' => ['nullable', 'date'],
            'schedules.*.notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $vehicleItem) {
            foreach (VehicleMaintenanceSchedule::TYPE_SELECT as $type => $label) {
                $values = $data['schedules'][$type] ?? [];
                $enabled = ! empty($values['enabled']);
                $intervalKm = $type === VehicleMaintenanceSchedule::TYPE_TIRE_ROTATION
                    ? VehicleMaintenanceSchedule::TIRE_ROTATION_INTERVAL_KM
                    : ($values['interval_km'] ?? null);

                if ($enabled && ! $intervalKm) {
                    throw ValidationException::withMessages([
                        "schedules.{$type}.interval_km" => "Indique a periodicidade para {$label}.",
                    ]);
                }

                if (! $enabled && ! $intervalKm) {
                    continue;
                }

                VehicleMaintenanceSchedule::updateOrCreate(
                    ['vehicle_item_id' => $vehicleItem->id, 'type' => $type],
                    [
                        'enabled' => $enabled,
                        'interval_km' => $intervalKm,
                        'last_service_km' => $values['last_service_km'] ?? null,
                        'last_service_date' => $values['last_service_date'] ?? null,
                        'notes' => $values['notes'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('admin.vehicle-maintenances.index')->with('message', 'Plano de manutenção atualizado com sucesso.');
    }

    public function complete(Request $request, VehicleMaintenanceSchedule $schedule)
    {
        abort_if(Gate::denies('vehicle_item_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'performed_km' => ['required', 'integer', 'min:0'],
            'performed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $schedule) {
            VehicleMaintenanceRecord::create([
                'vehicle_maintenance_schedule_id' => $schedule->id,
                'vehicle_item_id' => $schedule->vehicle_item_id,
                'type' => $schedule->type,
                'performed_km' => $data['performed_km'],
                'performed_at' => $data['performed_at'],
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $schedule->update([
                'last_service_km' => $data['performed_km'],
                'last_service_date' => $data['performed_at'],
            ]);
        });

        return back()->with('message', 'Manutenção registada e próxima quilometragem recalculada.');
    }
}
