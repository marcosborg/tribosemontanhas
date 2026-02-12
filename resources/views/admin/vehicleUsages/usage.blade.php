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
                            <li role="presentation" class="active">
                                <a href="#home" role="tab" data-toggle="tab">Linha do Tempo das Viaturas</a>
                            </li>
                            <li role="presentation">
                                <a href="#profile" role="tab" data-toggle="tab">Gráfico da Taxa de Ocupação</a>
                            </li>
                            <li role="presentation">
                                <a href="#messages" role="tab" data-toggle="tab">Detalhe da Ocupação por Viatura</a>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content">
                            <!-- Linha do Tempo -->
                            <div role="tabpanel" class="tab-pane active" id="home">
                            <h3>Linha do Tempo das Viaturas</h3>

                            {{-- Filtros para focar a timeline num mês específico --}}
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
                                    <label for="timelineMonthFilter" style="margin-right: 6px;">Mês:</label>
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

                            <div class="modal fade" id="usageModal" tabindex="-1" role="dialog" aria-labelledby="usageModalLabel">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                            <h4 class="modal-title" id="usageModalLabel">Vehicle usage</h4>
                                        </div>
                                        <div class="modal-body">
                                            <form id="usageForm">
                                                @csrf
                                                <input type="hidden" name="_method" id="usageFormMethod" value="POST">
                                                <input type="hidden" name="usage_id" id="usageId">
                                                <div id="usageFormErrors" class="alert alert-danger" style="display:none;"></div>

                                                <div class="form-group">
                                                    <label for="usage_driver_id">Driver</label>
                                                    <select class="form-control" name="driver_id" id="usage_driver_id"></select>
                                                </div>

                                                <div class="form-group">
                                                    <label class="required" for="usage_vehicle_item_id">Vehicle</label>
                                                    <select class="form-control" name="vehicle_item_id" id="usage_vehicle_item_id" required></select>
                                                </div>

                                                <div class="form-group">
                                                    <label class="required" for="usage_start_date">Start date</label>
                                                    <input class="form-control datetime" type="text" name="start_date" id="usage_start_date" required>
                                                </div>

                                                <div class="form-group">
                                                    <label for="usage_end_date">End date</label>
                                                    <input class="form-control datetime" type="text" name="end_date" id="usage_end_date">
                                                </div>

                                                <div class="form-group">
                                                    <label>Usage type</label>
                                                    @foreach(App\Models\VehicleUsage::USAGE_EXCEPTIONS_RADIO as $key => $label)
                                                        <div>
                                                            <input type="radio" id="usage_exceptions_{{ $key }}" name="usage_exceptions" value="{{ $key }}">
                                                            <label for="usage_exceptions_{{ $key }}" style="font-weight: 400">{{ $label }}</label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </form>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                            <button type="button" class="btn btn-primary" id="usageSaveBtn">Save</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                            <!-- Gráfico -->
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
                                        <label for="monthFilter" style="margin-right:6px;">Selecionar Mês:</label>
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

                                <h3 id="chartTitle" style="margin-top:20px;">Gráfico da Taxa de Ocupação</h3>

                                {{-- Altura controlada no CONTENTOR, não no canvas --}}
                                <div id="occupancyChartContainer" style="width:100%; height:420px; position:relative;">
                                    <canvas id="occupancyChart"></canvas>
                                </div>
                                <p class="text-muted" style="margin-top:10px;">
                                    As barras estão ordenadas por <strong>maior percentagem de utilização</strong> (verde). O rótulo no fim da barra é o <strong>aluguer da última semana</strong>.
                                </p>
                            </div>

                            <!-- Detalhe por Viatura -->
                            <div role="tabpanel" class="tab-pane" id="messages">
                                <h3 class="mt-5">Detalhe da Ocupação por Viatura</h3>
                                @foreach($occupancyStats as $plate => $years)
                                    <h4>{{ $plate }}</h4>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th style="width:60px; text-align:center;">#</th>
                                                <th>Ano</th>
                                                <th>Dias em uso</th>
                                                <th>Total de dias</th>
                                                <th>Taxa de ocupação (%)</th>
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
    const vehicleByPlate = @json(collect($grouped)->mapWithKeys(function ($records, $plate) {
        $vehicleItem = optional($records->first())->vehicle_item;
        return $plate && $vehicleItem ? [$plate => $vehicleItem->id] : [];
    })->all(), JSON_NUMERIC_CHECK);
    const plateByVehicleId = Object.keys(vehicleByPlate).reduce((acc, plate) => {
        acc[vehicleByPlate[plate]] = plate;
        return acc;
    }, {});

    const usageCreateUrl = "{{ url('/admin/vehicle-usages') }}";
    const usageEditBaseUrl = "{{ url('/admin/vehicle-usages') }}";
    const usageModal = document.getElementById('usageModal');
    const usageForm = document.getElementById('usageForm');
    const usageSaveBtn = document.getElementById('usageSaveBtn');
    const usageErrors = document.getElementById('usageFormErrors');
    const driverSelect = document.getElementById('usage_driver_id');
    const vehicleSelect = document.getElementById('usage_vehicle_item_id');
    const startInput = document.getElementById('usage_start_date');
    const endInput = document.getElementById('usage_end_date');
    const methodInput = document.getElementById('usageFormMethod');
    const usageIdInput = document.getElementById('usageId');

    let modalMode = 'create';

    function formatDateTime(d) {
        const pad = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }

    function showErrors(errors) {
        if (!usageErrors) return;
        if (!errors || !errors.length) {
            usageErrors.style.display = 'none';
            usageErrors.innerHTML = '';
            return;
        }
        usageErrors.innerHTML = errors.map(e => `<div>${e}</div>`).join('');
        usageErrors.style.display = 'block';
    }

    function openModal() {
        showErrors([]);
        if (window.jQuery) {
            $(usageModal).modal('show');
        } else {
            usageModal.style.display = 'block';
        }
    }

    function closeModal() {
        if (window.jQuery) {
            $(usageModal).modal('hide');
        } else {
            usageModal.style.display = 'none';
        }
    }

    function clearForm() {
        usageForm.reset();
        if (usageIdInput) usageIdInput.value = '';
        if (methodInput) methodInput.value = 'POST';
        modalMode = 'create';
    }

    async function loadOptionsIfNeeded() {
        if (driverSelect.options.length && vehicleSelect.options.length) {
            return;
        }
        const res = await fetch(`${usageEditBaseUrl}/create`, { credentials: 'same-origin' });
        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const driverOptions = doc.querySelectorAll('select[name="driver_id"] option');
        const vehicleOptions = doc.querySelectorAll('select[name="vehicle_item_id"] option');

        driverSelect.innerHTML = '';
        vehicleSelect.innerHTML = '';
        driverOptions.forEach(opt => driverSelect.appendChild(opt.cloneNode(true)));
        vehicleOptions.forEach(opt => vehicleSelect.appendChild(opt.cloneNode(true)));
    }

    function setUsageType(value) {
        const inputs = usageForm.querySelectorAll('input[name="usage_exceptions"]');
        inputs.forEach(i => { i.checked = i.value === value; });
    }

    function getSelectedDriverName() {
        const opt = driverSelect.options[driverSelect.selectedIndex];
        return opt ? opt.textContent.trim() : '';
    }

    function getUsageClassAndContent(driverName, usageType) {
        if (usageType) {
            const label = usageType.charAt(0).toUpperCase() + usageType.slice(1);
            return {
                className: `${usageType}-item`,
                content: driverName || (usageType === 'usage' ? 'Sem motorista' : label)
            };
        }
        if (driverName) {
            return { className: null, content: driverName };
        }
        return { className: 'exception-item', content: 'Sem motorista' };
    }

    async function openCreateModal(preselect) {
        await loadOptionsIfNeeded();
        clearForm();
        modalMode = 'create';
        methodInput.value = 'POST';
        if (preselect?.vehicleId) {
            vehicleSelect.value = String(preselect.vehicleId);
            vehicleSelect.setAttribute('disabled', 'disabled');
        } else {
            vehicleSelect.removeAttribute('disabled');
        }
        if (preselect?.startDate) {
            startInput.value = preselect.startDate;
        }
        setUsageType('usage');
        openModal();
    }

    async function openEditModal(usageId) {
        await loadOptionsIfNeeded();
        clearForm();
        modalMode = 'edit';
        usageIdInput.value = usageId;
        methodInput.value = 'PUT';
        vehicleSelect.removeAttribute('disabled');

        const res = await fetch(`${usageEditBaseUrl}/${usageId}/edit`, { credentials: 'same-origin' });
        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');

        const driverValue = doc.querySelector('[name="driver_id"]')?.value || '';
        const vehicleValue = doc.querySelector('[name="vehicle_item_id"]')?.value || '';
        const startValue = doc.querySelector('[name="start_date"]')?.value || '';
        const endValue = doc.querySelector('[name="end_date"]')?.value || '';
        const usageValue = doc.querySelector('input[name="usage_exceptions"]:checked')?.value || '';

        driverSelect.value = driverValue;
        vehicleSelect.value = vehicleValue;
        startInput.value = startValue;
        endInput.value = endValue;
        if (usageValue) {
            setUsageType(usageValue);
        }

        openModal();
    }

    async function fetchUsageIdByMatch(match) {
        const res = await fetch(`${usageEditBaseUrl}?ajax=1`, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const payload = await res.json();
        const list = payload?.data || [];
        const found = list.find(row => {
            const start = row.start_date || row.start || '';
            const end = row.end_date || row.end || '';
            return String(row.vehicle_item_id) === String(match.vehicle_item_id) &&
                String(row.driver_id || '') === String(match.driver_id || '') &&
                String(start) === String(match.start_date) &&
                String(end || '') === String(match.end_date || '');
        });
        return found ? found.id : null;
    }

    async function saveUsage() {
        showErrors([]);
        const formData = new FormData(usageForm);
        if (vehicleSelect.hasAttribute('disabled')) {
            formData.set('vehicle_item_id', vehicleSelect.value);
        }
        const url = modalMode === 'edit'
            ? `${usageEditBaseUrl}/${usageIdInput.value}`
            : usageCreateUrl;

        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: formData
        });

        if (res.status === 422) {
            const data = await res.json();
            const errors = [];
            Object.values(data.errors || {}).forEach(errArr => {
                (errArr || []).forEach(e => errors.push(e));
            });
            showErrors(errors);
            return;
        }

        if (!res.ok) {
            showErrors(['Ocorreu um erro ao guardar.']);
            return;
        }

        const driverName = getSelectedDriverName();
        const usageType = usageForm.querySelector('input[name="usage_exceptions"]:checked')?.value || '';
        const startVal = startInput.value;
        const endVal = endInput.value;
        const { className, content } = getUsageClassAndContent(driverName, usageType);

        if (modalMode === 'edit') {
            timelineItems.update({
                id: usageIdInput.value,
                content,
                start: startVal,
                end: endVal || null,
                group: plateByVehicleId[vehicleSelect.value] || null,
                className
            });
            closeModal();
            return;
        }

        const newId = await fetchUsageIdByMatch({
            vehicle_item_id: vehicleSelect.value,
            driver_id: driverSelect.value,
            start_date: startVal,
            end_date: endVal
        });

        if (newId) {
            timelineItems.add({
                id: newId,
                content,
                start: startVal,
                end: endVal || null,
                group: plateByVehicleId[vehicleSelect.value] || null,
                className
            });
        }

        closeModal();
    }

    // === TIMELINE ===
    const rawTimelineItems = @json($timelineItems, JSON_NUMERIC_CHECK);
    const safeTimelineItems = Array.isArray(rawTimelineItems) ? rawTimelineItems : [];
    const timelineItems = new vis.DataSet(
        safeTimelineItems.map(item => {
            if (!item.end) { delete item.end; }
            return item;
        })
    );

    // numerar grupos sem alterar a ordem de inserção
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
            groupOrder: function (a,b){ return 0; }, // manter ordem de inserção
            editable: false,
            margin: { item: 10, axis: 5 },
            orientation: 'top'
        }
    );

    timeline.on('click', function (props) {
        if (!props) return;

        if (props.item) {
            openEditModal(props.item);
            return;
        }

        if (!props.time || !props.group) {
            return;
        }

        const vehicleId = vehicleByPlate[props.group];
        if (!vehicleId) {
            return;
        }

        const dateStr = formatDateTime(new Date(props.time));
        openCreateModal({ vehicleId, startDate: dateStr });
    });

    if (usageSaveBtn) {
        usageSaveBtn.addEventListener('click', function () {
            saveUsage();
        });
    }

    // === Filtro de Ano/Mês para a TIMELINE ===
    const tYearSel  = document.getElementById('timelineYearFilter');
    const tMonthSel = document.getElementById('timelineMonthFilter');
    const tResetBtn = document.getElementById('timelineResetBtn');

    function focusTimelineMonth() {
        if (!timeline || !tYearSel || !tMonthSel) return;

        const year  = tYearSel.value;
        const month = tMonthSel.value;

        // Se algum estiver em "Todos", mostra o período completo
        if (year === 'all' || month === 'all') {
            timeline.fit({ animation: { duration: 400, easingFunction: 'easeInOutQuad' } });
            return;
        }

        const y = parseInt(year, 10);
        const m = parseInt(month, 10) - 1; // JS: 0 = Jan

        // 1º dia do mês
        const start = new Date(y, m, 1);
        // último dia do mês às 23:59:59
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

    // dados já reindexados pelo controller
    const rawStackedStats = @json($monthlyStackedStats, JSON_NUMERIC_CHECK);
    const stackedStats = Array.isArray(rawStackedStats) ? rawStackedStats : [];

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

    // Criar UMA instância de Chart
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
                // Mostra todos os valores do eixo X também (se quiser)
                ticks: { stepSize: 10, autoSkip: false, callback: v => v + '%' }
            },
            y: {
                stacked: true,
                // <- o que resolve os "pares" a desaparecer
                ticks: {
                autoSkip: false,   // não salta rótulos
                padding: 4,
                crossAlign: 'near' // evita cortar o texto
                },
                // dá um bocadinho mais de largura ao eixo para não cortar "1. ABC..."
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

        // filtra por ano/mês
        let filtered = stackedStats.filter(stat =>
            (year  === 'all' || stat.year  == year) &&
            (month === 'all' || stat.month == month)
        );

        // se mês = all, agrega por viatura(ano)
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

        // ordenar por % de utilização desc (verde)
        filtered.sort((a, b) => {
            const totA = categories.reduce((s, k) => s + (a[k] || 0), 0);
            const totB = categories.reduce((s, k) => s + (b[k] || 0), 0);
            const pA = totA ? (a.usage || 0) / totA : 0;
            const pB = totB ? (b.usage || 0) / totB : 0;
            if (pB === pA) return ('' + a.label).localeCompare(b.label); // tie-break estável
            return pB - pA;
        });

        // Altura dinâmica no CONTENTOR (evita loop de resize do canvas)
        const BAR_HEIGHT = 20; // px por item
        const targetHeight = Math.max(320, filtered.length * BAR_HEIGHT);
        if (container.style.height !== targetHeight + 'px') {
            container.style.height = targetHeight + 'px';
            chart.resize(); // pede ao chart para adaptar-se ao novo contentor
        }

        // === Labels numerados (1., 2., 3., ...) sem mexer na ordem ===
        chart.data.labels   = filtered.map((d, i) => `${i + 1}. ${d.label}`);
        chart.data.datasets = buildDatasets(filtered);

        // === Valores de aluguer no fim de cada barra (vêm do controller: stat.rent) ===
        chart.$_rents = filtered.map(stat => (stat.rent != null ? `${stat.rent} €` : '—'));

        chart.update();
    }

    // Recalcular quando a aba de gráfico for exibida
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
/* Canvas ocupa 100% do contentor */
#occupancyChart { width:100% !important; height:100% !important; }

/* Sticky headers for usage detail table */
#messages {
    max-height: calc(100vh - 260px);
    overflow-y: auto;
    overflow-x: auto;
    position: relative;
}
#messages table {
    width: 100%;
    border-collapse: separate;
}
#messages table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f5f5f5;
}

/* Sticky headers for timeline (years/months) */
#timelineContainer {
    max-height: calc(100vh - 260px);
    overflow: auto;
    position: relative;
}
#timelineContainer .vis-panel.vis-top {
    position: sticky !important;
    top: 0;
    z-index: 5;
    background: #ffffff;
}
#timelineContainer .vis-time-axis {
    background: #ffffff;
}

/* Cores da timeline por exceção */
.vis-item.usage-item      { background-color:#28a745 !important; border-color:#1e7e34 !important; color:#fff !important; font-weight:bold; }
.vis-item.maintenance-item{ background-color:#fd7e14 !important; border-color:#e8590c !important; color:#fff !important; font-weight:bold; }
.vis-item.accident-item   { background-color:#dc3545 !important; border-color:#a71d2a !important; color:#fff !important; font-weight:bold; }
.vis-item.unassigned-item { background-color:#ffc107 !important; border-color:#e0a800 !important; color:#333 !important; font-weight:bold; }
.vis-item.personal-item   { background-color:#6f42c1 !important; border-color:#5936a2 !important; color:#fff !important; font-weight:bold; }
.vis-item.exception-item  { background-color:#ff4d4d !important; border-color:#cc0000 !important; color:#fff !important; font-weight:bold; }
</style>
@endsection
