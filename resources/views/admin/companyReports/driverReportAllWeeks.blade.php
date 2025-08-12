@extends('layouts.admin')
@section('content')
<div class="content">
    @foreach ($drivers as $d)
    <a href="{{ route('admin.company-reports.driver-report-all-weeks', ['driver_id' => $d->id, 'state_id' => $state_id]) }}" class="btn btn-default {{ $driver_id == $d->id ? 'disabled selected' : '' }}" style="margin-top: 5px;">{{
        $d->name }} {{ $d->team->count() > 0 ? '(Team)' : '' }}</a>
    @endforeach
    @if ($state_id == 2)
    <a href="{{ route('admin.company-reports.driver-report-all-weeks', ['driver_id' => 0, 'state_id' => 1]) }}" class="btn btn-default">Ativos</a>
    @else
    <a href="{{ route('admin.company-reports.driver-report-all-weeks', ['driver_id' => 0, 'state_id' => 2]) }}" class="btn btn-default">Inativos</a>
    @endif
    <div class="panel panel-default" style="margin-top: 20px;">
        <div class="panel-heading">
            Resultados semanais do motorista selecionado
        </div>
        <div class="panel-body table-responsive">
            <table class="table table-bordered table-striped table-sm">
                <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Uber (Bruto)</th>
                        <th>Bolt (Bruto)</th>
                        <th>Uber (Líquido)</th>
                        <th>Bolt (Líquido)</th>
                        <th>Total Bruto</th>
                        <th>Total Líquido</th>
                        <th>Ajustes</th>
                        <th>Total Final</th>
                        <th>IVA</th>
                        <th>Car Track</th>
                        <th>Aluguer</th>
                        <th>Combustível</th>
                        <th>Saldo</th>
                        <th>Transferido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results as $r)
                    <tr>
                        <td>
                            {{ \Carbon\Carbon::parse($r['week']->start_date)->format('d/m') }}
                            a
                            {{ \Carbon\Carbon::parse($r['week']->end_date)->format('d/m') }}
                        </td>
                        <td>{{ number_format($r['uber_gross'], 2, ',', '.') }} €</td>
                        <td>{{ number_format($r['bolt_gross'], 2, ',', '.') }} €</td>
                        <td>{{ number_format($r['uber_net'], 2, ',', '.') }} €</td>
                        <td>{{ number_format($r['bolt_net'], 2, ',', '.') }} €</td>
                        <td><strong>{{ number_format($r['total_gross'], 2, ',', '.') }} €</strong></td>
                        <td>{{ number_format($r['total_net'], 2, ',', '.') }} €</td>
                        <td>{{ number_format($r['adjustments'], 2, ',', '.') }} €</td>
                        <td><strong>{{ number_format($r['total'], 2, ',', '.') }} €</strong></td>
                        <td>{{ number_format($r['vat_value'], 2, ',', '.') }} €</td>
                        <td>{{ number_format($r['car_track'], 2, ',', '.') }} €</td>
                        <td>{{ number_format($r['car_hire'], 2, ',', '.') }} €</td>
                        <td>{{ number_format($r['fuel_transactions'], 2, ',', '.') }} €</td>
                        <td>
                            <strong style="color: {{ $r['driver_balance'] < 0 ? 'red' : 'green' }}">
                                {{ number_format($r['driver_balance'], 2, ',', '.') }} €
                            </strong>
                        </td>
                        <td>{{ number_format($r['amount_transferred'], 2, ',', '.') }} €</td>
                    </tr>
                    @endforeach
                    @if (count($results) == 0)
                    <tr>
                        <td colspan="14">Sem resultados disponíveis para este motorista.</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
