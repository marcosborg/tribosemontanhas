@extends('layouts.admin')
@section('content')
<div class="content">
    @can('driver_deposit_movement_create')
        <a class="btn btn-success" href="{{ route('admin.driver-deposit-real-movements.create') }}">Adicionar movimento</a>
    @endcan
    <a class="btn btn-default" href="{{ route('admin.driver-deposit-real-movements.index', array_merge(request()->query(), ['export' => 'excel'])) }}">Exportar Excel</a>
    <a class="btn btn-default" href="{{ route('admin.driver-deposit-real-movements.index', array_merge(request()->query(), ['export' => 'csv'])) }}">CSV</a>
    <a class="btn btn-default" href="{{ route('admin.driver-deposit-real-movements.index', array_merge(request()->query(), ['export' => 'pdf'])) }}">PDF</a>

    <div class="panel panel-default" style="margin-top: 15px;">
        <div class="panel-heading">Movimentos reais de caucao</div>
        <div class="panel-body">
            <form method="GET" action="{{ route('admin.driver-deposit-real-movements.index') }}" class="row" style="margin-bottom: 15px;">
                <div class="col-md-2"><select class="form-control select2" name="driver_id"><option value="">Motorista</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" {{ ($filters['driver_id'] ?? '') == $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><select class="form-control select2" name="company_id"><option value="">Empresa</option>@foreach($companies as $company)<option value="{{ $company->id }}" {{ ($filters['company_id'] ?? '') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><select class="form-control" name="type"><option value="">Tipo</option>@foreach($types as $key => $label)<option value="{{ $key }}" {{ ($filters['type'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-2"><input class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                <div class="col-md-2"><input class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
                <div class="col-md-2"><button class="btn btn-primary" type="submit">Filtrar</button></div>
            </form>

            <table class="table table-bordered table-striped table-hover datatable datatable-DepositMovements">
                <thead>
                    <tr>
                        <th></th>
                        <th>Data</th>
                        <th>Motorista</th>
                        <th>Empresa</th>
                        <th>Semana</th>
                        <th>Tipo</th>
                        <th style="text-align:right;">Valor</th>
                        <th>Metodo</th>
                        <th>Descricao</th>
                        <th>Criado por</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($movements as $movement)
                        <tr>
                            <td></td>
                            <td>{{ optional($movement->created_at)->format('Y-m-d') }}</td>
                            <td>{{ $movement->driver->name ?? '' }}</td>
                            <td>{{ $movement->company->name ?? '' }}</td>
                            <td>{{ $movement->tvde_week->start_date ?? '' }}</td>
                            <td>{{ \App\Models\DriverDepositMovement::REAL_TYPE_SELECT[$movement->type] ?? $movement->type }}</td>
                            <td style="text-align:right;">{{ number_format($movement->amount, 2) }} &euro;</td>
                            <td>{{ $movement->payment_method }}</td>
                            <td>{{ $movement->description }}</td>
                            <td>{{ $movement->creator->name ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@section('scripts')
@parent
<script>
    $(function () {
        $('.datatable-DepositMovements').DataTable({ order: [[1, 'desc']], pageLength: 100 });
    });
</script>
@endsection
