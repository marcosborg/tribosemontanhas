@extends('layouts.admin')
@section('content')

<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Caucao
                </div>

                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-green"><i class="fas fa-shield-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Saldo atual de caucao</span>
                                    <span class="info-box-number">{{ number_format($currentBalance, 2) }} <small>EUR</small></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Empresa</th>
                                        <th>Estado</th>
                                        <th style="text-align:right;">Valor total</th>
                                        <th style="text-align:right;">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($deposits as $deposit)
                                        <tr>
                                            <td>{{ $deposit->company->name ?? '' }}</td>
                                            <td>{{ \App\Models\DriverDeposit::STATUS_SELECT[$deposit->status] ?? $deposit->status }}</td>
                                            <td style="text-align:right;">{{ number_format($deposit->total_amount, 2) }} EUR</td>
                                            <td style="text-align:right;">{{ number_format($depositBalances[$deposit->id] ?? 0, 2) }} EUR</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">Nao existem caucoes registadas.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <h4>Registo de pagamentos</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover datatable datatable-MyDriverDeposit">
                            <thead>
                                <tr>
                                    <th width="10"></th>
                                    <th>Semana</th>
                                    <th>Empresa</th>
                                    <th>Tipo</th>
                                    <th>Descricao</th>
                                    <th style="text-align:right;">Valor</th>
                                    <th style="text-align:right;">Saldo apos movimento</th>
                                    <th>Conta corrente</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($movements as $movement)
                                    <tr>
                                        <td></td>
                                        <td>{{ $movement->tvde_week ? $movement->tvde_week->start_date . ' a ' . $movement->tvde_week->end_date : '' }}</td>
                                        <td>{{ $movement->deposit->company->name ?? '' }}</td>
                                        <td>{{ \App\Models\DriverDepositMovement::TYPE_SELECT[$movement->type] ?? $movement->type }}</td>
                                        <td>{{ $movement->description }}</td>
                                        <td style="text-align:right;">{{ number_format($movement->amount, 2) }} EUR</td>
                                        <td style="text-align:right;">{{ number_format($movement->balance_after, 2) }} EUR</td>
                                        <td>
                                            @if($movement->affects_statement)
                                                <span class="label label-success">Sim</span>
                                            @else
                                                <span class="label label-default">Nao</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
        $('.datatable-MyDriverDeposit').DataTable({
            order: [[1, 'desc']],
            pageLength: 100
        });
    });
</script>
@endsection
