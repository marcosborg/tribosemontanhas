@extends('layouts.admin')

@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-md-8">
                            {{ trans('cruds.vehicleUsage.title') }} - Visão Geral
                        </div>
                        <div class="col-md-4">
                            <a href="/admin/vehicle-usages/create" class="btn btn-primary btn-sm pull-right">Vehicle usage</a>
                        </div>
                    </div>
                </div>

                <div class="panel-body">
                    <div>
                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs" role="tablist">
                            <li role="presentation" class="active"><a href="#home" role="tab" data-toggle="tab">Linha do Tempo das Viaturas</a></li>
                            <li role="presentation"><a href="#profile" role="tab" data-toggle="tab">Gráfico da Taxa de Ocupação</a></li>
                            <li role="presentation"><a href="#messages" role="tab" data-toggle="tab">Detalhe da Ocupação por Viatura</a></li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content">
                            <!-- Linha do Tempo -->
                            <div role="tabpanel" class="tab-pane active" id="home">
                                <h3>Linha do Tempo das Viaturas</h3>
                                <div id="timelineContainer" style="margin-bottom: 40px;">
                                    <div id="timeline" style="height: auto;"></div>
                                </div>
                            </div>

                            <!-- Gráfico -->
                            <div role="tabpanel" class="tab-pane" id="profile">
                                <div class="form-group mt-3">
                                    <label for="yearFilter">Selecionar Ano:</label>
                                    <select id="yearFilter" class="form-control" style="max-width: 200px;">
                                        <option value="all">Todos os anos</option>
                                        @foreach(array_keys($availableYears) as $year)
                                            <option value="{{ $year }}">{{ $year }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group mt-3">
                                    <label for="monthFilter">Selecionar Mês:</label>
                                    <select id="monthFilter" class="form-control" style="max-width: 200px;">
                                        <option value="all">Todos os meses</option>
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                                        @endfor
                                    </select>
                                </div>

                                <h3 id="chartTitle">Gráfico da Taxa de Ocupação</h3>
                                <canvas id="occupancyChart" style="width: 100%; height: 400px;"></canvas>
                            </div>

                            <!-- Detalhe por Viatura -->
                            <div role="tabpanel" class="tab-pane" id="messages">
                                <h3 class="mt-5">Detalhe da Ocupação por Viatura</h3>
                                @foreach($occupancyStats as $plate => $years)
                                    <h4>{{ $plate }}</h4>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Ano</th>
                                                <th>Dias em uso</th>
                                                <th>Total de dias</th>
                                                <th>Taxa de ocupação (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($years as $year => $data)
                                                <tr>
                                                    <td>{{ $year }}</td>
                                                    <td>{{ $data['used'] }}</td>
                                                    <td>{{ $data['total'] }}</td>
                                                    <td>{{ $data['percent'] }}%</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<link href="https://unpkg.com/vis-timeline@latest/styles/vis-timeline-graph2d.min.css" rel="stylesheet" />
<script src="https://unpkg.com/vis-timeline@latest/standalone/umd/vis-timeline-graph2d.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // === TIMELINE ===
    const timelineItems = new vis.DataSet([
        @foreach($grouped as $plate => $records)
            @foreach($records as $record)
                {
                    id: {{ $record->id }},
                    content: '{{ addslashes($record->driver ? $record->driver->name : ($record->usage_exceptions ? ucfirst($record->usage_exceptions) : 'Sem motorista')) }}',
                    start: '{{ \Carbon\Carbon::parse($record->getRawOriginal("start_date"))->toDateString() }}',
                    end: '{{ \Carbon\Carbon::parse($record->getRawOriginal("end_date"))->toDateString() }}',
                    group: '{{ $plate }}',
                    @if($record->usage_exceptions)
                        className: '{{ $record->usage_exceptions }}-item',
                    @elseif(!$record->driver)
                        className: 'exception-item',
                    @endif
                },
            @endforeach
        @endforeach
    ]);

    const timelineGroups = new vis.DataSet([
        @foreach($grouped as $plate => $records)
            { id: '{{ $plate }}', content: '{{ $plate }}' },
        @endforeach
    ]);

    new vis.Timeline(document.getElementById('timeline'), timelineItems, timelineGroups, {
        stack: false,
        groupOrder: 'content',
        editable: false,
        margin: { item: 10, axis: 5 },
        orientation: 'top'
    });

    // === CHART.JS ===
    const ctx = document.getElementById('occupancyChart').getContext('2d');

    const monthlyStats = @json(array_values($monthlyStats));
    const yearlyStats = @json($yearlyStats);

    let chart;

    function updateChart(data) {
        const labels = data.map(d => d.label);
        const values = data.map(d => d.percent);

        if (chart) chart.destroy();

        chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Taxa de Ocupação (%)',
                    data: values,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: value => value + '%'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: context => context.raw + '%'
                        }
                    }
                }
            }
        });
    }

    function filterStats() {
        const year = document.getElementById('yearFilter').value;
        const month = document.getElementById('monthFilter').value;
        let filtered;

        if (month !== 'all') {
            filtered = monthlyStats.filter(d =>
                (year === 'all' || d.year === year) && d.month === month
            );
        } else {
            filtered = year === 'all'
                ? [...yearlyStats]
                : yearlyStats.filter(d => d.year === year);
        }

        // Ordenar do mais utilizado para o menos utilizado
        filtered.sort((a, b) => b.percent - a.percent);

        updateChart(filtered);
    }


    document.getElementById('yearFilter').addEventListener('change', filterStats);
    document.getElementById('monthFilter').addEventListener('change', filterStats);

    updateChart(yearlyStats); // inicial
});
</script>
@endsection


@section('styles')
<style>
    .vis-item.usage-item {
    background-color: #28a745 !important; /* Verde */
    border-color: #1e7e34 !important;
    color: white !important;
    font-weight: bold;
}

.vis-item.maintenance-item {
    background-color: #fd7e14 !important; /* Laranja */
    border-color: #e8590c !important;
    color: white !important;
    font-weight: bold;
}

.vis-item.accident-item {
    background-color: #dc3545 !important; /* Vermelho */
    border-color: #a71d2a !important;
    color: white !important;
    font-weight: bold;
}

.vis-item.unassigned-item {
    background-color: #ffc107 !important; /* Amarelo */
    border-color: #e0a800 !important;
    color: #333 !important;
    font-weight: bold;
}

.vis-item.personal-item {
    background-color: #6f42c1 !important; /* Roxo */
    border-color: #5936a2 !important;
    color: white !important;
    font-weight: bold;
}

.vis-item.exception-item {
    background-color: #ff4d4d !important; /* Vermelho (sem motorista) */
    border-color: #cc0000 !important;
    color: white !important;
    font-weight: bold;
}

/* Opcional: estilizar explicitamente a utilização normal (sem usage_exceptions, com driver) */
.vis-item.default-usage-item {
    background-color: #007bff !important; /* Azul padrão */
    border-color: #0056b3 !important;
    color: white !important;
    font-weight: bold;
}

</style>
@endsection
