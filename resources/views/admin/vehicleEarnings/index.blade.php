@extends('layouts.admin')
@section('content')
<div class="content">
    @if ($company_id == 0)
    <div class="alert alert-info" role="alert">
        Selecione uma empresa para ver os seus extratos.
    </div>
    @else
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
    @endif
    <div class="row" style="margin-top: 20px;">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Viaturas sem faturação
                </div>
                <div class="panel-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Condutor</th>
                                <th>Rentabilidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>AA-00-11</td>
                                <td>Peugeot</td>
                                <td>308</td>
                                <td>João Martins</td>
                                <td style="color: red;">-123.45 €</td>
                            </tr>
                            <tr>
                                <td>BB-22-33</td>
                                <td>Renault</td>
                                <td>Clio</td>
                                <td>Maria Sousa</td>
                                <td style="color: orange;">0.00 €</td>
                            </tr>
                            <tr>
                                <td>CC-44-55</td>
                                <td>Volkswagen</td>
                                <td>Golf</td>
                                <td>Carlos Dias</td>
                                <td style="color: red;">-89.90 €</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
