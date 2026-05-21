@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Detalhe de reconciliacao - {{ $driver->name }}</div>
        <div class="panel-body">
            <a class="btn btn-default" href="{{ route('admin.driver-deposit-reconciliation.index') }}">Voltar</a>

            <div class="row" style="margin-top: 15px;">
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fas fa-calendar"></i></span><div class="info-box-content"><span class="info-box-text">Total previsto</span><span class="info-box-number">{{ number_format($summary['planned'], 2) }} &euro;</span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-green"><i class="fas fa-euro-sign"></i></span><div class="info-box-content"><span class="info-box-text">Total pago</span><span class="info-box-number">{{ number_format($summary['received'], 2) }} &euro;</span></div></div></div>
                <div class="col-md-2"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fas fa-clock"></i></span><div class="info-box-content"><span class="info-box-text">Em divida</span><span class="info-box-number">{{ number_format($summary['debt'], 2) }} &euro;</span></div></div></div>
                <div class="col-md-2"><div class="info-box"><span class="info-box-icon bg-red"><i class="fas fa-undo"></i></span><div class="info-box-content"><span class="info-box-text">Devolucoes</span><span class="info-box-number">{{ number_format($summary['refunds'], 2) }} &euro;</span></div></div></div>
                <div class="col-md-2"><div class="info-box"><span class="info-box-icon bg-purple"><i class="fas fa-cash-register"></i></span><div class="info-box-content"><span class="info-box-text">Saldo</span><span class="info-box-number">{{ number_format($summary['balance'], 2) }} &euro;</span></div></div></div>
            </div>

            <h4>Timeline</h4>
            <table class="table table-bordered table-striped table-hover datatable datatable-Timeline">
                <thead>
                    <tr>
                        <th></th>
                        <th>Data</th>
                        <th>Origem</th>
                        <th>Semana</th>
                        <th>Descricao</th>
                        <th style="text-align:right;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeline as $item)
                        <tr>
                            <td></td>
                            <td>{{ $item['date'] }}</td>
                            <td>{{ $item['kind'] }}</td>
                            <td>{{ $item['week'] }}</td>
                            <td>{{ $item['label'] }}</td>
                            <td style="text-align:right;">{{ number_format($item['amount'], 2) }} &euro;</td>
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
        $('.datatable-Timeline').DataTable({ order: [[1, 'asc']], pageLength: 100 });
    });
</script>
@endsection
