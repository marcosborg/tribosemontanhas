@extends('layouts.admin')
@section('content')
<div class="content">

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.vehicleProfitability.title') }}
                </div>
                <div class="panel-body">
                    <div class="btn-group btn-group-justified" role="group">
                        @foreach ($tvde_years as $tvde_year)
                        <a href="/admin/financial-statements/year/{{ $tvde_year->id }}" class="btn btn-default {{ $tvde_year->id == $tvde_year_id ? 'disabled selected' : '' }}">{{ $tvde_year->name
                            }}</a>
                        @endforeach
                    </div>
                    <div class="btn-group btn-group-justified" role="group" style="margin-top: 5px;">
                        @foreach ($tvde_months as $tvde_month)
                        <a href="/admin/financial-statements/month/{{ $tvde_month->id }}" class="btn btn-default {{ $tvde_month->id == $tvde_month_id ? 'disabled selected' : '' }}">{{
                            $tvde_month->name
                            }}</a>
                        @endforeach
                    </div>
                    <div class="btn-group btn-group-justified" role="group" style="margin-top: 5px;">
                        @foreach ($tvde_weeks as $tvde_week)
                        <a href="/admin/financial-statements/week/{{ $tvde_week->id }}" class="btn btn-default {{ $tvde_week->id == $tvde_week_id ? 'disabled selected' : '' }}">Semana de {{
                            \Carbon\Carbon::parse($tvde_week->start_date)->format('d')
                            }} a {{ \Carbon\Carbon::parse($tvde_week->end_date)->format('d') }}</a>
                        @endforeach
                    </div>
                    <ul class="nav nav-tabs">
                        @foreach ($vehicle_items as $vehicle_item)
                        <li role="presentation" {{ $vehicle_item_id == $vehicle_item->id ? 'class=active' : '' }}>
                            <a href="/admin/vehicle-profitabilities/set-vehicle-item-id/{{ $vehicle_item->id }}">{{ $vehicle_item->license_plate }} {{ $vehicle_item->driver ? '(' . $vehicle_item->driver->name . ')' : '' }}</a>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            Tesouraria
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Motorista</strong> ({{ $driver->name }})<br>
                                    Liquido: {{ $results->total_net }}<br>
                                    Portagens: {{ $results->car_track }}<br>
                                    Gasóleo: {{ $results->fuel_transactions }}<br>
                                    Ajustes: {{ $results->adjustments }}<br>
                                    Retenção na fonte: {{ $driver_balance->final }}<br>
                                    Salário: {{ $driver_balance->balance }}<br>
                                </div>
                                <div class="col-md-6">
                                    <strong>Viatura</strong><br>
                                    Despesas: {{ $vehicle_expenses['vehicle_expenses_value'] }}<br>
                                    Devoluções: {{ $expense_reimbursements_value }}<br>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            IVA
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Motorista</strong><br>
                                    IVA da faturação bruta: {{ $results->vat_value }}<br>
                                    IVA do recibo verde: {{ $driver_balance->iva }}<br>
                                    IVA do gasóleo: {{ $fuel_transactions_vat }}<br>
                                </div>
                                <div class="col-md-6">
                                    <strong>Viatura</strong><br>
                                    Manutenções com IVA: {{ $vehicle_expenses['vehicle_expenses_vat'] }}<br>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
<script>console.log({!! json_encode($results) !!})</script>