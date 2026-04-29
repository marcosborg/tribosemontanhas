@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">Caução</div>
                <div class="panel-body">
                    <a class="btn btn-default" href="{{ route('admin.driver-deposits.index') }}">Voltar</a>
                    @can('driver_deposit_edit')
                        <a class="btn btn-info" href="{{ route('admin.driver-deposits.edit', $driverDeposit) }}">Editar</a>
                    @endcan

                    <table class="table table-bordered table-striped" style="margin-top: 15px;">
                        <tbody>
                            <tr><th>Motorista</th><td>{{ $driverDeposit->driver->name ?? '' }}</td></tr>
                            <tr><th>Empresa</th><td>{{ $driverDeposit->company->name ?? $driverDeposit->driver->company->name ?? '' }}</td></tr>
                            <tr><th>Valor total</th><td>{{ number_format($driverDeposit->total_amount, 2) }} €</td></tr>
                            <tr><th>Pagamento inicial</th><td>{{ number_format($driverDeposit->initial_payment, 2) }} €</td></tr>
                            <tr><th>Valor semanal</th><td>{{ number_format($driverDeposit->weekly_amount, 2) }} €</td></tr>
                            <tr><th>Saldo disponível</th><td>{{ number_format($availableBalance, 2) }} €</td></tr>
                            <tr><th>Estado</th><td>{{ \App\Models\DriverDeposit::STATUS_SELECT[$driverDeposit->status] ?? $driverDeposit->status }}</td></tr>
                        </tbody>
                    </table>

                    @can('driver_deposit_edit')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="panel panel-default">
                                    <div class="panel-heading">Debitar à caução</div>
                                    <div class="panel-body">
                                        <form method="POST" action="{{ route('admin.driver-deposits.internal-debit', $driverDeposit) }}">
                                            @csrf
                                            @include('admin.driverDeposits.partials.movementForm', ['defaultAmount' => '', 'button' => 'Registar abatimento'])
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="panel panel-default">
                                    <div class="panel-heading">Devolver caução</div>
                                    <div class="panel-body">
                                        <form method="POST" action="{{ route('admin.driver-deposits.refund', $driverDeposit) }}">
                                            @csrf
                                            @include('admin.driverDeposits.partials.movementForm', ['defaultAmount' => $availableBalance, 'button' => 'Registar devolução', 'defaultWeekId' => $suggestedRefundWeekId])
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endcan

                    <h4>Movimentos</h4>
                    <table class="table table-bordered table-striped table-hover datatable datatable-DriverDepositLedger">
                        <thead>
                            <tr>
                                <th>Semana</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th style="text-align:right;">Valor</th>
                                <th style="text-align:right;">Saldo caução</th>
                                <th>Extrato</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movements as $movement)
                                <tr>
                                    <td>{{ $movement->tvde_week->start_date ?? '' }}</td>
                                    <td>{{ \App\Models\DriverDepositMovement::TYPE_SELECT[$movement->type] ?? $movement->type }}</td>
                                    <td>{{ $movement->description }}</td>
                                    <td style="text-align:right;">{{ number_format($movement->amount, 2) }} €</td>
                                    <td style="text-align:right;">{{ number_format($movement->balance_after, 2) }} €</td>
                                    <td>{{ $movement->affects_statement ? 'Sim' : 'Nao' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
@parent
<script>
    $(function () {
        $('.datatable-DriverDepositLedger').DataTable({
            order: [[0, 'asc']],
            pageLength: 100
        });
    });
</script>
@endsection
