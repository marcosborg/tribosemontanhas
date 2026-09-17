@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Configurar manutenções — {{ $vehicleItem->license_plate }}</div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.vehicle-maintenances.update', $vehicleItem) }}">
                @csrf
                @method('PUT')
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Ativa</th>
                            <th>Manutenção</th>
                            <th>Periodicidade (km)</th>
                            <th>Última manutenção (km)</th>
                            <th>Data da última manutenção</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($types as $type => $label)
                            @php
                                $schedule = $schedules->get($type);
                                $isTireRotation = $type === \App\Models\VehicleMaintenanceSchedule::TYPE_TIRE_ROTATION;
                                $defaultInterval = $isTireRotation ? \App\Models\VehicleMaintenanceSchedule::TIRE_ROTATION_INTERVAL_KM : null;
                                $enabled = old("schedules.{$type}.enabled", $schedule?->enabled ?? $isTireRotation);
                            @endphp
                            <tr>
                                <td style="text-align:center;">
                                    <input type="hidden" name="schedules[{{ $type }}][enabled]" value="0">
                                    <input type="checkbox" name="schedules[{{ $type }}][enabled]" value="1" {{ $enabled ? 'checked' : '' }}>
                                </td>
                                <td>{{ $label }}</td>
                                <td>
                                    <input class="form-control" type="number" min="1" name="schedules[{{ $type }}][interval_km]"
                                           value="{{ old("schedules.{$type}.interval_km", $schedule?->interval_km ?? $defaultInterval) }}"
                                           {{ $isTireRotation ? 'readonly' : '' }}>
                                </td>
                                <td><input class="form-control" type="number" min="0" name="schedules[{{ $type }}][last_service_km]" value="{{ old("schedules.{$type}.last_service_km", $schedule?->last_service_km) }}"></td>
                                <td><input class="form-control" type="date" name="schedules[{{ $type }}][last_service_date]" value="{{ old("schedules.{$type}.last_service_date", optional($schedule?->last_service_date)->format('Y-m-d')) }}"></td>
                                <td><input class="form-control" type="text" name="schedules[{{ $type }}][notes]" value="{{ old("schedules.{$type}.notes", $schedule?->notes) }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-muted">A rotação dos pneus está definida em 15 000 km. Nas restantes manutenções pode indicar a periodicidade de cada viatura.</p>
                <button class="btn btn-danger" type="submit">Guardar</button>
                <a class="btn btn-default" href="{{ route('admin.vehicle-maintenances.index') }}">Voltar</a>
            </form>
        </div>
    </div>
</div>
@endsection
