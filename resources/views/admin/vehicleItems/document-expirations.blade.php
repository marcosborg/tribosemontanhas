@extends('layouts.admin')

@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div style="margin-bottom: 10px;">
                <a class="btn btn-default" href="{{ route('admin.vehicle-items.index') }}">
                    Voltar
                </a>
                @can('vehicle_item_edit')
                    <button class="btn btn-danger" form="document-expirations-form" type="submit">
                        Guardar datas
                    </button>
                @endcan
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    Validade de documentos das viaturas
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('admin.vehicle-items.document-expirations.update') }}" id="document-expirations-form">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover datatable datatable-DocumentExpirations vehicle-document-expirations-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Matrícula</th>
                                        <th>Empresa</th>
                                        <th>Marca</th>
                                        <th>Modelo</th>
                                        @foreach($documentExpirationFields as $field => $label)
                                            <th>{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($vehicleItems as $vehicleItem)
                                        <tr>
                                            <td></td>
                                            <td>{{ $vehicleItem->license_plate }}</td>
                                            <td>{{ $vehicleItem->company->name ?? '' }}</td>
                                            <td>{{ $vehicleItem->vehicle_brand->name ?? '' }}</td>
                                            <td>{{ $vehicleItem->vehicle_model->name ?? '' }}</td>
                                            @foreach($documentExpirationFields as $field => $label)
                                                @php
                                                    $date = $vehicleItem->{$field};
                                                    $value = old("vehicles.{$vehicleItem->id}.{$field}", $date ? $date->format('Y-m-d') : '');
                                                    $statusClass = '';

                                                    if ($date) {
                                                        $days = now()->startOfDay()->diffInDays($date, false);
                                                        $statusClass = $days < 0 ? 'document-expired' : ($days <= 7 ? 'document-urgent' : ($days <= 30 ? 'document-soon' : ''));
                                                    }
                                                @endphp
                                                <td class="{{ $statusClass }}">
                                                    <input
                                                        class="form-control input-sm document-date-input"
                                                        type="date"
                                                        name="vehicles[{{ $vehicleItem->id }}][{{ $field }}]"
                                                        value="{{ $value }}"
                                                        @cannot('vehicle_item_edit') disabled @endcannot
                                                    >
                                                    @if($errors->has("vehicles.{$vehicleItem->id}.{$field}"))
                                                        <span class="help-block" role="alert">{{ $errors->first("vehicles.{$vehicleItem->id}.{$field}") }}</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
@parent
<style>
    .vehicle-document-expirations-table th,
    .vehicle-document-expirations-table td {
        white-space: nowrap;
        vertical-align: middle !important;
    }

    .vehicle-document-expirations-table .document-date-input {
        min-width: 145px;
    }

    .vehicle-document-expirations-table td.document-expired {
        background: #f2dede !important;
    }

    .vehicle-document-expirations-table td.document-urgent {
        background: #fcf8e3 !important;
    }

    .vehicle-document-expirations-table td.document-soon {
        background: #dff0d8 !important;
    }
</style>
@endsection

@section('scripts')
@parent
<script>
    $(function () {
        $('.datatable-DocumentExpirations').DataTable({
            order: [[1, 'asc']],
            pageLength: 100,
            scrollX: true,
            buttons: $.fn.dataTable.defaults.buttons
        });
    });
</script>
@endsection
