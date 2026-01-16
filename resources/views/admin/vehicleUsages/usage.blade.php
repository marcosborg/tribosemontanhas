@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-md-8">
                            {{ trans('cruds.vehicleUsage.title') }} - VisÃ£o Geral
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
                            <li role="presentation" class="active">
                                <a href="#home" role="tab" data-toggle="tab">Linha do Tempo das Viaturas</a>
                            </li>
                            <li role="presentation">
                                <a href="#profile" role="tab" data-toggle="tab">GrÃ¡fico da Taxa de OcupaÃ§Ã£o</a>
                            </li>
                            <li role="presentation">
                                <a href="#messages" role="tab" data-toggle="tab">Detalhe da OcupaÃ§Ã£o por Viatura</a>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content">
                            <!-- Linha do Tempo -->
                            <div role="tabpanel" class="tab-pane active" id="home">
                            <h3>Linha do Tempo das Viaturas</h3>

                            {{-- Filtros para focar a timeline num mÃªs especÃ­fico --}}
                            <div class="form-inline" style="margin-bottom: 15px;">
                                <div class="form-group" style="margin-right: 10px;">
                                    <label for="timelineYearFilter" style="margin-right: 6px;">Ano:</label>
                                    <select id="timelineYearFilter" class="form-control" style="max-width: 200px;">
                                        <option value="all">Todos</option>
                                        @foreach(array_keys($availableYears) as $year)
                                            <option value="{{ $year }}">{{ $year }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group" style="margin-right: 10px;">
                                    <label for="timelineMonthFilter" style="margin-right: 6px;">MÃªs:</label>
                                    <select id="timelineMonthFilter" class="form-control" style="max-width: 200px;">
                                        <option value="all">Todos</option>
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">
                                                {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>

                                <button id="timelineResetBtn" class="btn btn-default btn-sm">
                                    Ver tudo
                                </button>
                            </div>

                            <div id="timelineContainer" style="margin-bottom: 40px;">
                                <div id="timeline" style="height: auto;"></div>
                            </div>
                        </div>


                            <!-- GrÃ¡fico -->
                            <div role="tabpanel" class="tab-pane" id="profile">
                                <div class="form-inline" style="margin-top:15px;">
                                    <div class="form-group" style="margin-right:10px;">
                                        <label for="yearFilter" style="margin-right:6px;">Selecionar Ano:</label>
                                        <select id="yearFilter" class="form-control" style="max-width: 200px;">
                                            <option value="all">Todos os anos</option>
                                            @foreach(array_keys($availableYears) as $year)
                                                <option value="{{ $year }}">{{ $year }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="monthFilter" style="margin-right:6px;">Selecionar MÃªs:</label>
                                        <select id="monthFilter" class="form-control" style="max-width: 200px;">
                                            <option value="all">Todos os meses</option>
                                            @for ($m = 1; $m <= 12; $m++)
                                                <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">
                                                    {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>

                                <h3 id="chartTitle" style="margin-top:20px;">GrÃ¡fico da Taxa de OcupaÃ§Ã£o</h3>

                                {{-- Altura controlada no CONTÃŠINER, nÃ£o no canvas --}}
                                <div id="occupancyChartContainer" style="width:100%; height:420px; position:relative;">
                                    <canvas id="occupancyChart"></canvas>
                                </div>
                                <p class="text-muted" style="margin-top:10px;">
                                    As barras estÃ£o ordenadas por <strong>maior percentagem de utilizaÃ§Ã£o</strong> (verde). O rÃ³tulo no fim da barra Ã© o <strong>aluguer da Ãºltima semana</strong>.
                                </p>
                            </div>

                            <!-- Detalhe por Viatura -->
                            <div role="tabpanel" class="tab-pane" id="messages">
                                <h3 class="mt-5">Detalhe da OcupaÃ§Ã£o por Viatura</h3>
                                @foreach($occupancyStats as $plate => $years)
                                    <h4>{{ $plate }}</h4>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th style="width:60px; text-align:center;">#</th>
                                                <th>Ano</th>
                                                <th>Dias em uso</th>
                                                <th>Total de dias</th>
                                                <th>Taxa de ocupaÃ§Ã£o (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($years as $year => $data)
                                                <tr>
                                                    <td style="text-align:center;">{{ $loop->iteration }}</td>
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
                        </div><!-- /.tab-content -->
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
    const rawTimelineItems = @json($timelineItems, JSON_NUMERIC_CHECK);
    const timelineItems = new vis.DataSet(
        rawTimelineItems.map(item => {
            if (!item.end) { delete item.end; }
            return item;
        })
    );

    // numerar grupos sem alterar a ordem de inserÃ§Ã£o
    const timelineGroups = new vis.DataSet([
        @php $__grp_i = 1; @endphp
        @foreach($grouped as $plate => $records)
            { id: '{{ $plate }}', content: '{{ $__grp_i++ }}. {{ $plate }}' },
        @endforeach
    ]);

    const timeline = new vis.Timeline(
        document.getElementById('timeline'),
        timelineItems,
        timelineGroups,
        {
            stack: false,
            groupOrder: function (a,b){ return 0; }, // manter ordem de inserÃ§Ã£o
            editable: false,
            margin: { item: 10, axis: 5 },
            orientation: 'top'
        }
    );

    // === Filtro de Ano/MÃªs para a TIMELINE ===
    const tYearSel  = document.getElementById('timelineYearFilter');
    const tMonthSel = document.getElementById('timelineMonthFilter');
    const tResetBtn = document.getElementById('timelineResetBtn');

    function focusTimelineMonth() {
        if (!timeline || !tYearSel || !tMonthSel) return;

        const year  = tYearSel.value;
        const month = tMonthSel.value;

        // Se algum estiver em "Todos", mostra o perÃ­odo completo
        if (year === 'all' || month === 'all') {
            timeline.fit({ animation: { duration: 400, easingFunction: 'easeInOutQuad' } });
            return;
        }

        const y = parseInt(year, 10);
        const m = parseInt(month, 10) - 1; // JS: 0 = Jan

        // 1Âº dia do mÃªs
        const start = new Date(y, m, 1);
        // Ãºltimo dia do mÃªs Ã s 23:59:59
        const end   = new Date(y, m + 1, 0, 23, 59, 59, 999);

        timeline.setWindow(start, end, {
            animation: { duration: 400, easingFunction: 'easeInOutQuad' }
        });
    }

    if (tYearSel && tMonthSel) {
        tYearSel.addEventListener('change', focusTimelineMonth);
        tMonthSel.addEventListener('change', focusTimelineMonth);
    }

    if (tResetBtn) {
        tResetBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (tYearSel)  tYearSel.value  = 'all';
            if (tMonthSel) tMonthSel.value = 'all';
            timeline.fit({ animation: { duration: 400, easingFunction: 'easeInOutQuad' } });
        });
    }


    // === CHART.JS (STACKED HORIZONTAL) ===
    const ctx        = document.getElementById('occupancyChart').getContext('2d');
    const container  = document.getElementById('occupancyChartContainer');

    // dados jÃ¡ reindexados pelo controller
    const stackedStats = @json($monthlyStackedStats, JSON_NUMERIC_CHECK);

    const categoryLabels = {
        usage: 'UtilizaÃ§Ã£o',
        maintenance: 'ManutenÃ§Ã£o',
        accident: 'Sinistrado',
        unassigned: 'Sem utilizaÃ§Ã£o',
        personal: 'UtilizaÃ§Ã£o pessoal'
    };
    const categoryColors = {
        usage: '#28a745',
        maintenance: '#fd7e14',
        accident: '#dc3545',
        unassigned: '#ffc107',
        personal: '#6f42c1'
    };
    const categories = ['usage', 'maintenance', 'accident', 'unassigned', 'personal'];

    // --- Plugin: valor do aluguer no fim de cada barra (usa stat.rent) ---
    const rentLabelPlugin = {
        id: 'rentLabelPlugin',
        afterDatasetsDraw(chart, args, pluginOptions) {
            const { ctx, scales } = chart;
            const yScale = scales.y;
            const xScale = scales.x;
            const rents = chart.$_rents || []; // array alinhado com chart.data.labels

            ctx.save();
            ctx.textBaseline = 'middle';

            chart.data.labels.forEach((_, i) => {
                const y = yScale.getPixelForValue(i);
                const x = xScale.getPixelForValue(100) + 6; // ligeiro offset depois do 100%
                const text = rents[i] ?? '';
                if (!text) return;

                ctx.fillStyle = '#111';
                ctx.fillText(String(text), x, y);
            });

            ctx.restore();
        }
    };

    // Criar UMA instÃ¢ncia de Chart
    const chart = new Chart(ctx, {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { right: 64 } },

            plugins: {
            tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw}%` } },
            legend: { position: 'bottom' }
            },

            scales: {
            x: {
                stacked: true,
                min: 0,
                max: 100,
                // Mostra todos os valores do eixo X tambÃ©m (se quiser)
                ticks: { stepSize: 10, autoSkip: false, callback: v => v + '%' }
            },
            y: {
                stacked: true,
                // <- o que resolve os â€œparesâ€ a desaparecer
                ticks: {
                autoSkip: false,   // nÃ£o salta rÃ³tulos
                padding: 4,
                crossAlign: 'near' // evita cortar o texto
                },
                // dÃ¡ um bocadinho mais de largura ao eixo para nÃ£o cortar "1. ABC..."
                afterFit(scale) { scale.width += 24; }
            }
            }
        },
        plugins: [rentLabelPlugin]
        });


    function buildDatasets(filteredData) {
        return categories.map(cat => ({
            label: categoryLabels[cat],
            backgroundColor: categoryColors[cat],
            data: filteredData.map(stat => {
                const total = categories.reduce((sum, key) => sum + (stat[key] || 0), 0);
                return total > 0 ? +(((stat[cat] || 0) / total) * 100).toFixed(2) : 0;
            }),
            stack: 'ocupacao'
        }));
    }

    function filterStackedStats() {
        const year  = document.getElementById('yearFilter').value;
        const month = document.getElementById('monthFilter').value;

        // filtra por ano/mÃªs
        let filtered = stackedStats.filter(stat =>
            (year  === 'all' || stat.year  == year) &&
            (month === 'all' || stat.month == month)
        );

        // se mÃªs = all, agrega por viatura(ano)
        if (month === 'all') {
            const grouped = {};
            filtered.forEach(stat => {
                const key = `${stat.plate} (${stat.year})`;
                if (!grouped[key]) {
                    grouped[key] = {
                        label: key,
                        plate: stat.plate,
                        year: stat.year,
                        usage: 0, maintenance: 0, accident: 0, unassigned: 0, personal: 0,
                        rent: stat.rent ?? null // manter rent ao agregar
                    };
                }
                categories.forEach(cat => grouped[key][cat] += (stat[cat] || 0));
            });
            filtered = Object.values(grouped);
        }

        // ordenar por % de utilizaÃ§Ã£o desc (verde)
        filtered.sort((a, b) => {
            const totA = categories.reduce((s, k) => s + (a[k] || 0), 0);
            const totB = categories.reduce((s, k) => s + (b[k] || 0), 0);
            const pA = totA ? (a.usage || 0) / totA : 0;
            const pB = totB ? (b.usage || 0) / totB : 0;
            if (pB === pA) return ('' + a.label).localeCompare(b.label); // tie-break estÃ¡vel
            return pB - pA;
        });

        // Altura dinÃ¢mica no CONTÃŠINER (evita loop de resize do canvas)
        const BAR_HEIGHT = 20; // px por item
        const targetHeight = Math.max(320, filtered.length * BAR_HEIGHT);
        if (container.style.height !== targetHeight + 'px') {
            container.style.height = targetHeight + 'px';
            chart.resize(); // pede ao chart para adaptar-se ao novo contÃªiner
        }

        // === Labels numerados (1., 2., 3., â€¦) sem mexer na ordem ===
        chart.data.labels   = filtered.map((d, i) => `${i + 1}. ${d.label}`);
        chart.data.datasets = buildDatasets(filtered);

        // === Valores de aluguer no fim de cada barra (vÃªm do controller: stat.rent) ===
        chart.$_rents = filtered.map(stat => (stat.rent != null ? `${stat.rent} â‚¬` : 'â€”'));

        chart.update();
    }

    // Recalcular quando a aba de grÃ¡fico for exibida
    const tabLink = document.querySelector('a[href="#profile"]');
    if (tabLink) {
        if (window.jQuery) {
            $(tabLink).on('shown.bs.tab', () => { chart.resize(); filterStackedStats(); });
        } else {
            tabLink.addEventListener('click', () => {
                setTimeout(() => { chart.resize(); filterStackedStats(); }, 0);
            });
        }
    }

    document.getElementById('yearFilter').addEventListener('change', filterStackedStats);
    document.getElementById('monthFilter').addEventListener('change', filterStackedStats);

    // Render inicial
    filterStackedStats();
});
</script>
@endsection

@section('styles')
<style>
/* Canvas ocupa 100% do contÃªiner */
#occupancyChart { width:100% !important; height:100% !important; }

/* Cores da timeline por exceÃ§Ã£o */
.vis-item.usage-item      { background-color:#28a745 !important; border-color:#1e7e34 !important; color:#fff !important; font-weight:bold; }
.vis-item.maintenance-item{ background-color:#fd7e14 !important; border-color:#e8590c !important; color:#fff !important; font-weight:bold; }
.vis-item.accident-item   { background-color:#dc3545 !important; border-color:#a71d2a !important; color:#fff !important; font-weight:bold; }
.vis-item.unassigned-item { background-color:#ffc107 !important; border-color:#e0a800 !important; color:#333 !important; font-weight:bold; }
.vis-item.personal-item   { background-color:#6f42c1 !important; border-color:#5936a2 !important; color:#fff !important; font-weight:bold; }
.vis-item.exception-item  { background-color:#ff4d4d !important; border-color:#cc0000 !important; color:#fff !important; font-weight:bold; }
</style>
@endsection
