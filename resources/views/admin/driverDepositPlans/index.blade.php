@extends('layouts.admin')
@section('content')
<div class="content">
    @can('driver_deposit_plan_create')
        <a class="btn btn-success" href="{{ route('admin.driver-deposit-plans.create') }}">Adicionar plano</a>
    @endcan
    <a class="btn btn-default" href="{{ route('admin.driver-deposit-plans.index', array_merge(request()->query(), ['export' => 'csv'])) }}">Exportar Excel</a>

    <div class="panel panel-default" style="margin-top: 15px;">
        <div class="panel-heading">Planeamento de caucoes</div>
        <div class="panel-body">
            <form method="GET" action="{{ route('admin.driver-deposit-plans.index') }}" class="row" style="margin-bottom: 15px;">
                <div class="col-md-2">
                    <select class="form-control select2" name="driver_id">
                        <option value="">Motorista</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}" {{ ($filters['driver_id'] ?? '') == $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control select2" name="company_id">
                        <option value="">Empresa</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ ($filters['company_id'] ?? '') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value="">Estado</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['status'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><input class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                <div class="col-md-2"><input class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
                <div class="col-md-2"><button class="btn btn-primary" type="submit">Filtrar</button></div>
            </form>

            <table class="table table-bordered table-striped table-hover datatable datatable-DepositPlans">
                <thead>
                    <tr>
                        <th></th>
                        <th>Motorista</th>
                        <th>Empresa</th>
                        <th>Entrada</th>
                        <th>Semanal</th>
                        <th>Semanas</th>
                        <th>Estado</th>
                        <th>Total previsto</th>
                        <th>Total pago</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($plans as $plan)
                        <tr>
                            <td></td>
                            <td>{{ $plan->driver->name ?? '' }}</td>
                            <td>{{ $plan->company->name ?? '' }}</td>
                            <td>{{ number_format($plan->initial_amount, 2) }} &euro;</td>
                            <td>{{ number_format($plan->weekly_amount, 2) }} &euro;</td>
                            <td>{{ $plan->total_weeks }}</td>
                            <td>{{ \App\Models\DriverDepositPlan::STATUS_SELECT[$plan->status] ?? $plan->status }}</td>
                            <td>{{ number_format($plan->items->sum('amount'), 2) }} &euro;</td>
                            <td>{{ number_format($plan->items->sum('paid_amount'), 2) }} &euro;</td>
                            <td>
                                @can('driver_deposit_plan_show')
                                    <a class="btn btn-xs btn-primary" href="{{ route('admin.driver-deposit-plans.show', $plan) }}">Ver plano</a>
                                @endcan
                                @can('driver_deposit_plan_edit')
                                    <a class="btn btn-xs btn-info" href="{{ route('admin.driver-deposit-plans.edit', $plan) }}">Editar</a>
                                    @if($plan->status === \App\Models\DriverDepositPlan::STATUS_PAUSED)
                                        <form method="POST" action="{{ route('admin.driver-deposit-plans.reactivate', $plan) }}" style="display:inline-block">@csrf<button class="btn btn-xs btn-success" type="submit">Reativar</button></form>
                                    @else
                                        <form method="POST" action="{{ route('admin.driver-deposit-plans.pause', $plan) }}" style="display:inline-block">@csrf<button class="btn btn-xs btn-warning" type="submit">Pausar</button></form>
                                    @endif
                                @endcan
                            </td>
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
        $('.datatable-DepositPlans').DataTable({ order: [[1, 'asc']], pageLength: 50 });
    });
</script>
@endsection
