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
    // === TIMELINE === (sem alterações)
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

    // === CHART.JS === STACKED ===
    const ctx = document.getElementById('occupancyChart').getContext('2d');
    const stackedStats = @json(array_values($monthlyStackedStats));

    const categoryLabels = {
        usage: 'Utilização',
        maintenance: 'Manutenção',
        accident: 'Sinistrado',
        unassigned: 'Sem utilização',
        personal: 'Utilização pessoal'
    };

    const categoryColors = {
        usage: '#28a745',
        maintenance: '#fd7e14',
        accident: '#dc3545',
        unassigned: '#ffc107',
        personal: '#6f42c1'
    };

    const categories = ['usage', 'maintenance', 'accident', 'unassigned', 'personal'];

    let chart;

    function updateStackedChart(filteredData) {
        const labels = filteredData.map(d => d.label);

        const datasets = categories.map(cat => ({
            label: categoryLabels[cat],
            backgroundColor: categoryColors[cat],
            data: filteredData.map(stat => {
                const total = categories.reduce((sum, key) => sum + stat[key], 0);
                return total > 0 ? (stat[cat] / total * 100).toFixed(2) : 0;
            }),
            stack: 'ocupacao'
        }));

        if (chart) chart.destroy();

        chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: context => `${context.dataset.label}: ${context.raw}%`
                        }
                    },
                    legend: { position: 'bottom' }
                },
                scales: {
                    x: {
                        stacked: true,
                        max: 100,
                        ticks: {
                            callback: value => value + '%'
                        }
                    },
                    y: { stacked: true }
                }
            }
        });
    }

    function filterStackedStats() {
        const year = document.getElementById('yearFilter').value;
        const month = document.getElementById('monthFilter').value;

        let filtered = stackedStats.filter(stat =>
            (year === 'all' || stat.year === year) &&
            (month === 'all' || stat.month === month)
        );

        // Se mês for "all", agrupamos por viatura (independentemente do ano)
        if (month === 'all') {
            const grouped = {};

            filtered.forEach(stat => {
                const label = `${stat.plate} (${stat.year})`; // exemplo: AA-00-XX (2025)
                if (!grouped[label]) {
                    grouped[label] = {
                        label: label,
                        usage: 0,
                        maintenance: 0,
                        accident: 0,
                        unassigned: 0,
                        personal: 0,
                    };
                }

                categories.forEach(cat => {
                    grouped[label][cat] += stat[cat];
                });
            });

            filtered = Object.values(grouped);
        }

        // Ordenar pelo valor de 'usage' decrescente (mais bem ocupado primeiro)
        filtered.sort((a, b) => b.usage - a.usage);

        // 📏 Altura dinâmica por barra (30px por entrada)
        const BAR_HEIGHT = 10;
        const canvas = document.getElementById('occupancyChart');
        canvas.height = filtered.length * BAR_HEIGHT;

        updateStackedChart(filtered);
    }


    document.getElementById('yearFilter').addEventListener('change', filterStackedStats);
    document.getElementById('monthFilter').addEventListener('change', filterStackedStats);

    filterStackedStats(); // inicial
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
