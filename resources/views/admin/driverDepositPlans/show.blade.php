@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Detalhe do plano de caucao</div>
        <div class="panel-body">
            <a class="btn btn-default" href="{{ route('admin.driver-deposit-plans.index') }}">Voltar</a>
            @can('driver_deposit_plan_edit')
                <a class="btn btn-info" href="{{ route('admin.driver-deposit-plans.edit', $driverDepositPlan) }}">Editar</a>
                <form method="POST" action="{{ route('admin.driver-deposit-plans.recalculate', $driverDepositPlan) }}" style="display:inline-block">
                    @csrf
                    <button class="btn btn-warning" type="submit">Recalcular</button>
                </form>
            @endcan

            <div class="row" style="margin-top: 15px;">
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fas fa-calendar-alt"></i></span><div class="info-box-content"><span class="info-box-text">Total previsto</span><span class="info-box-number">{{ number_format($totalPlanned, 2) }} &euro;</span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-green"><i class="fas fa-check"></i></span><div class="info-box-content"><span class="info-box-text">Total pago</span><span class="info-box-number">{{ number_format($totalPaid, 2) }} &euro;</span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fas fa-clock"></i></span><div class="info-box-content"><span class="info-box-text">Parcelas futuras</span><span class="info-box-number">{{ number_format($futureTotal, 2) }} &euro;</span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-red"><i class="fas fa-exclamation"></i></span><div class="info-box-content"><span class="info-box-text">Vencidas</span><span class="info-box-number">{{ number_format($overdueTotal, 2) }} &euro;</span></div></div></div>
            </div>

            <table class="table table-bordered table-striped">
                <tbody>
                    <tr><th>Motorista</th><td>{{ $driverDepositPlan->driver->name ?? '' }}</td></tr>
                    <tr><th>Empresa</th><td>{{ $driverDepositPlan->company->name ?? '' }}</td></tr>
                    <tr><th>Estado</th><td>{{ \App\Models\DriverDepositPlan::STATUS_SELECT[$driverDepositPlan->status] ?? $driverDepositPlan->status }}</td></tr>
                    <tr><th>Semana inicial</th><td>{{ $driverDepositPlan->start_week->start_date ?? '' }}</td></tr>
                </tbody>
            </table>

            <h4>Parcelas previstas</h4>
            <table class="table table-bordered table-striped table-hover datatable datatable-PlanItems">
                <thead>
                    <tr>
                        <th></th>
                        <th>Semana</th>
                        <th>Data prevista</th>
                        <th>Valor</th>
                        <th>Pago</th>
                        <th>Estado</th>
                        <th>Pago em</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td></td>
                            <td>{{ $item->tvde_week->start_date ?? '' }}</td>
                            <td>{{ optional($item->due_date)->format('Y-m-d') }}</td>
                            <td>{{ number_format($item->amount, 2) }} &euro;</td>
                            <td>{{ number_format($item->paid_amount, 2) }} &euro;</td>
                            <td>{{ \App\Models\DriverDepositPlanItem::STATUS_SELECT[$item->status] ?? $item->status }}</td>
                            <td>{{ optional($item->paid_at)->format('Y-m-d H:i') }}</td>
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
        $('.datatable-PlanItems').DataTable({ order: [[2, 'asc']], pageLength: 100 });
    });
</script>
@endsection
