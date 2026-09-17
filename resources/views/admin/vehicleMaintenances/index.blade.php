@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Manutenções das viaturas</div>
        <div class="panel-body">
            <form method="GET" action="{{ route('admin.vehicle-maintenances.index') }}" class="form-inline" style="margin-bottom: 15px;">
                <div class="form-group">
                    <label for="company_id">Empresa</label>
                    <select class="form-control select2" name="company_id" id="company_id">
                        <option value="">Todas</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ (string) $companyId === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Filtrar</button>
            </form>

            <table class="table table-bordered table-striped table-hover datatable datatable-VehicleMaintenances">
                <thead>
                    <tr>
                        <th>Viatura</th>
                        <th>Empresa</th>
                        <th>Quilómetros atuais</th>
                        <th>Manutenção</th>
                        <th>Periodicidade</th>
                        <th>Última</th>
                        <th>Próxima</th>
                        <th>Estado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($vehicleItems as $vehicleItem)
                        @php
                            $enabledSchedules = $vehicleItem->maintenance_schedules->where('enabled', true)->sortBy('type');
                            $currentKm = $vehicleItem->current_km !== null ? (float) $vehicleItem->current_km : null;
                        @endphp
                        @forelse($enabledSchedules as $schedule)
                            @php
                                $nextKm = $schedule->nextServiceKm();
                                $status = $schedule->statusFor($currentKm);
                                $statusLabels = ['due' => 'Vencida', 'soon' => 'Próxima', 'ok' => 'Em dia', 'unknown' => 'Sem dados'];
                                $statusClasses = ['due' => 'danger', 'soon' => 'warning', 'ok' => 'success', 'unknown' => 'default'];
                            @endphp
                            <tr>
                                <td>{{ $vehicleItem->license_plate }}</td>
                                <td>{{ $vehicleItem->company->name ?? '' }}</td>
                                <td>{{ $currentKm !== null ? number_format($currentKm, 0, ',', ' ') . ' km' : 'Sem leitura' }}</td>
                                <td>{{ $types[$schedule->type] ?? $schedule->type }}</td>
                                <td>{{ number_format($schedule->interval_km, 0, ',', ' ') }} km</td>
                                <td>
                                    {{ $schedule->last_service_km !== null ? number_format($schedule->last_service_km, 0, ',', ' ') . ' km' : 'Por registar' }}
                                    @if($schedule->last_service_date)<br><small>{{ $schedule->last_service_date->format('d/m/Y') }}</small>@endif
                                </td>
                                <td>{{ $nextKm !== null ? number_format($nextKm, 0, ',', ' ') . ' km' : 'Por calcular' }}</td>
                                <td><span class="label label-{{ $statusClasses[$status] }}">{{ $statusLabels[$status] }}</span></td>
                                <td style="white-space: nowrap;">
                                    <button type="button" class="btn btn-xs btn-success register-maintenance"
                                            data-toggle="modal" data-target="#maintenanceModal"
                                            data-action="{{ route('admin.vehicle-maintenance-schedules.complete', $schedule) }}"
                                            data-label="{{ $types[$schedule->type] ?? $schedule->type }} — {{ $vehicleItem->license_plate }}"
                                            data-km="{{ $currentKm !== null ? (int) round($currentKm) : '' }}">
                                        Registar
                                    </button>
                                    <a class="btn btn-xs btn-info" href="{{ route('admin.vehicle-maintenances.edit', $vehicleItem) }}">Configurar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td>{{ $vehicleItem->license_plate }}</td>
                                <td>{{ $vehicleItem->company->name ?? '' }}</td>
                                <td>{{ $currentKm !== null ? number_format($currentKm, 0, ',', ' ') . ' km' : 'Sem leitura' }}</td>
                                <td colspan="5">Manutenções por configurar</td>
                                <td><a class="btn btn-xs btn-info" href="{{ route('admin.vehicle-maintenances.edit', $vehicleItem) }}">Configurar</a></td>
                            </tr>
                        @endforelse
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="maintenanceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="maintenanceCompleteForm">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Registar manutenção</h4>
                </div>
                <div class="modal-body">
                    <p id="maintenanceLabel" class="text-muted"></p>
                    <div class="form-group">
                        <label class="required" for="performed_km">Quilómetros da viatura</label>
                        <input class="form-control" type="number" min="0" name="performed_km" id="performed_km" required>
                    </div>
                    <div class="form-group">
                        <label class="required" for="performed_at">Data</label>
                        <input class="form-control" type="date" name="performed_at" id="performed_at" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="maintenance_notes">Notas</label>
                        <textarea class="form-control" name="notes" id="maintenance_notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Registar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script>
$(function () {
    $('.datatable-VehicleMaintenances').DataTable({ pageLength: 100, order: [[0, 'asc'], [3, 'asc']] });
    $(document).on('click', '.register-maintenance', function () {
        $('#maintenanceCompleteForm').attr('action', $(this).data('action'));
        $('#maintenanceLabel').text($(this).data('label'));
        $('#performed_km').val($(this).data('km'));
        $('#maintenance_notes').val('');
    });
});
</script>
@endsection
