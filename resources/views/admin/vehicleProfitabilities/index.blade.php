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
                    <ul class="nav nav-tabs">
                        @foreach ($vehicle_items as $vehicle_item)
                        <li role="presentation" {{ $vehicle_item_id == $vehicle_item->id ? 'class="active"' : '' }}>
                            <a href="/admin/vehicle-profitabilities/set-vehicle-item-id/{{ $vehicle_item->id }}">{{ $vehicle_item->license_plate }} {{ $vehicle_item->driver ? '(' . $vehicle_item->driver->name . ')' : '' }}</a>
                        </li>
                        @endforeach
                    </ul>
                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-6">
                            <form action="/admin/vehicle-profitabilities/set-interval" method="post">
                                @csrf
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Data inicial</label>
                                        <input type="date" name="start_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Data final</label>
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-success form-control">Obter dados</button>
                                    </div>
                                </div>
                            </form>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Exercício Total</th>
                                        <th>Tesouraria</th>
                                        <th>IVA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($datas as $data)
                                    <tr>
                                        <td>{{ $data->tvde_week->start_date }}</td>
                                        <td>{{ number_format($data->total, 2) }} €</td>
                                        <td>{{ number_format($data->total_exercise, 2) }} €</td>
                                        <td>{{ number_format($data->vats, 2) }} €</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <canvas id="profitabilityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Dados obtidos do servidor para o gráfico
        const labels = @json(array_map(function($data) { return $data->tvde_week->start_date; }, $datas));
        const exerciseTotalData = @json(array_map(function($data) { return $data->total; }, $datas));
        const treasuryData = @json(array_map(function($data) { return $data->total_exercise; }, $datas));
        const ivaData = @json(array_map(function($data) { return $data->vats; }, $datas));

        // Configuração do gráfico Chart.js
        const ctx = document.getElementById('profitabilityChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Exercício Total',
                        data: exerciseTotalData,
                        backgroundColor: 'rgba(255, 159, 64, 0.7)',
                    },
                    {
                        label: 'Tesouraria',
                        data: treasuryData,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    },
                    {
                        label: 'IVA',
                        data: ivaData,
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        beginAtZero: true
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
@endsection

