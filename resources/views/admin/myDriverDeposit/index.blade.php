@extends('layouts.admin')
@section('content')

<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Caução
                </div>

                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-green"><i class="fas fa-shield-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Saldo atual de caução</span>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($deposits as $deposit)
                                        <tr>
                                            <td>{{ $deposit->company->name ?? '' }}</td>
                                            <td>{{ \App\Models\DriverDeposit::STATUS_SELECT[$deposit->status] ?? $deposit->status }}</td>
                                            <td style="text-align:right;">{{ number_format($deposit->total_amount, 2) }} EUR</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3">Não existem cauções registadas.</td>
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
                                    <th>Descrição</th>
                                    <th style="text-align:right;">Valor</th>
                                    <th>Pagamento</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($movements as $movement)
                                    <tr>
                                        <td></td>
                                        <td>{{ $movement->tvde_week ? $movement->tvde_week->start_date . ' a ' . $movement->tvde_week->end_date : '' }}</td>
                                        <td>{{ $movement->deposit->company->name ?? '' }}</td>
                                        <td>{{ $movement->type_label }}</td>
                                        <td>{{ strtr($movement->description ?? '', ['Caucao' => 'Caução', 'caucao' => 'caução', 'Devolucao' => 'Devolução', 'devolucao' => 'devolução', 'validacao' => 'validação', 'Regularizacao' => 'Regularização']) }}</td>
                                        <td style="text-align:right;">{{ number_format($movement->amount, 2) }} EUR</td>
                                        <td>
                                            @if($movement->payment_status_label === 'Pago')
                                                <span class="label label-success">✓ Pago</span>
                                            @elseif($movement->payment_status_label === 'Por pagar')
                                                <span class="label label-warning">✗ Por pagar</span>
                                            @else
                                                {{ $movement->payment_status_label }}
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
