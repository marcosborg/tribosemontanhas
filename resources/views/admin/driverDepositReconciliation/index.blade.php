@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-2"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fas fa-calendar"></i></span><div class="info-box-content"><span class="info-box-text">Previsto</span><span class="info-box-number">{{ number_format($cards['planned'], 2) }} &euro;</span></div></div></div>
        <div class="col-md-2"><div class="info-box"><span class="info-box-icon bg-green"><i class="fas fa-euro-sign"></i></span><div class="info-box-content"><span class="info-box-text">Recebido</span><span class="info-box-number">{{ number_format($cards['received'], 2) }} &euro;</span></div></div></div>
        <div class="col-md-2"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fas fa-clock"></i></span><div class="info-box-content"><span class="info-box-text">Em divida</span><span class="info-box-number">{{ number_format($cards['debt'], 2) }} &euro;</span></div></div></div>
        <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-red"><i class="fas fa-undo"></i></span><div class="info-box-content"><span class="info-box-text">Devolvidas</span><span class="info-box-number">{{ number_format($cards['refunds'], 2) }} &euro;</span></div></div></div>
        <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-purple"><i class="fas fa-cash-register"></i></span><div class="info-box-content"><span class="info-box-text">Valor em caixa</span><span class="info-box-number">{{ number_format($cards['cash'], 2) }} &euro;</span></div></div></div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">Reconciliacao de caucoes</div>
        <div class="panel-body">
            <form method="GET" action="{{ route('admin.driver-deposit-reconciliation.index') }}" class="row" style="margin-bottom: 15px;">
                <div class="col-md-3"><select class="form-control select2" name="company_id"><option value="">Empresa</option>@foreach($companies as $company)<option value="{{ $company->id }}" {{ ($filters['company_id'] ?? '') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><select class="form-control select2" name="driver_id"><option value="">Motorista</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" {{ ($filters['driver_id'] ?? '') == $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label><input type="checkbox" name="only_debt" value="1" {{ !empty($filters['only_debt']) ? 'checked' : '' }}> Apenas divida</label></div>
                <div class="col-md-2"><label><input type="checkbox" name="only_positive_balance" value="1" {{ !empty($filters['only_positive_balance']) ? 'checked' : '' }}> Saldo positivo</label></div>
                <div class="col-md-2"><button class="btn btn-primary" type="submit">Filtrar</button></div>
            </form>

            <table class="table table-bordered table-striped table-hover datatable datatable-Reconciliation">
                <thead>
                    <tr>
                        <th></th>
                        <th>Motorista</th>
                        <th>Empresa</th>
                        <th style="text-align:right;">Previsto</th>
                        <th style="text-align:right;">Recebido</th>
                        <th style="text-align:right;">Em divida</th>
                        <th style="text-align:right;">Saldo</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td></td>
                            <td>{{ $row['driver']->name ?? '' }}</td>
                            <td>{{ $row['company']->name ?? '' }}</td>
                            <td style="text-align:right;">{{ number_format($row['planned'], 2) }} &euro;</td>
                            <td style="text-align:right;">{{ number_format($row['received'], 2) }} &euro;</td>
                            <td style="text-align:right;">{{ number_format($row['debt'], 2) }} &euro;</td>
                            <td style="text-align:right;">{{ number_format($row['balance'], 2) }} &euro;</td>
                            <td><a class="btn btn-xs btn-primary" href="{{ route('admin.driver-deposit-reconciliation.show', ['driver' => $row['driver']->id ?? 0, 'company_id' => $row['company']->id ?? null]) }}">Detalhe</a></td>
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
        $('.datatable-Reconciliation').DataTable({ order: [[1, 'asc']], pageLength: 100 });
    });
</script>
@endsection
