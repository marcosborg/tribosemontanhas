@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Cauções globais</div>
        <div class="panel-body">
            <p>Uma linha por motorista, incluindo inativos e cauções liquidadas. Todos os motoristas com histórico até à data final são incluídos.</p>
            <form method="GET" action="{{ route('admin.driver-deposit-global.index') }}" class="row" style="margin-bottom:20px;">
                <div class="col-md-3 form-group">
                    <label for="date_from">Data inicial</label>
                    <input class="form-control" id="date_from" name="date_from" type="date" value="{{ old('date_from', $from) }}" required>
                </div>
                <div class="col-md-3 form-group">
                    <label for="date_to">Data final</label>
                    <input class="form-control" id="date_to" name="date_to" type="date" value="{{ old('date_to', $to) }}" required>
                </div>
                <div class="col-md-6" style="padding-top:25px;">
                    <button class="btn btn-primary" type="submit">Filtrar</button>
                    <button class="btn btn-success" type="submit" name="export" value="xlsx">Exportar Excel</button>
                    <a class="btn btn-default" href="{{ route('admin.driver-deposits.index') }}">Ver movimentos</a>
                </div>
            </form>
            <p><strong>Período: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</strong> · {{ $rows->count() }} motoristas</p>
            <div class="row">
                @foreach(['opening_total' => 'Saldo inicial', 'total_received' => 'Recebido no período', 'total_refunded' => 'Devolvido no período', 'total_balance' => 'Saldo final'] as $key => $label)
                    <div class="col-md-3"><div class="well well-sm"><div>{{ $label }}</div><strong>{{ number_format($totals[$key], 2, ',', '.') }} €</strong></div></div>
                @endforeach
            </div>
            <p class="help-block">{{ \App\Services\DriverDepositGlobalReportService::NOTES }}</p>
            @if($rows->contains(fn ($row) => $row['other_received'] != 0 || $row['other_refunded'] != 0 || $row['other_balance'] != 0))
                <p class="alert alert-warning">Há valores sem atribuição a pagamento inicial ou semanal. Consulte as colunas “Por classificar” e “Saldo não atribuído”; estes valores estão incluídos nos totais.</p>
            @endif
            <div class="table-responsive">
                <table class="table table-bordered table-striped" style="white-space:nowrap;">
                    <thead><tr>@foreach($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>@foreach($columns as $key => $label)<td @if(!in_array($key, ['driver_id', 'name', 'companies'])) style="text-align:right;" @endif>{{ in_array($key, ['driver_id', 'name', 'companies']) ? $row[$key] : number_format($row[$key], 2, ',', '.') . ' €' }}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}">Sem movimentos de caução até à data final selecionada.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr>@foreach($columns as $key => $label)<th>{{ $key === 'name' ? 'TOTAL' : (isset($totals[$key]) ? number_format($totals[$key], 2, ',', '.') . ' €' : '') }}</th>@endforeach</tr></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
