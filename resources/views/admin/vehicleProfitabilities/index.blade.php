@extends('layouts.admin')

@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">

            {{-- Título --}}
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.vehicleProfitability.title') ?? 'Vehicle Profitability' }}
                </div>

                {{-- FILTROS --}}
                <div class="panel-body">
                    <form method="GET" action="{{ route('admin.vehicle-profitabilities.index') }}" class="form-inline" id="filtersForm">
                        {{-- Período --}}
                        <div class="form-group" style="margin-right:8px;">
                            <label for="period">Período:&nbsp;</label>
                            <select name="period" id="period" class="form-control">
                                <option value="week"  {{ $period=='week'  ? 'selected' : '' }}>Semanas</option>
                                <option value="month" {{ $period=='month' ? 'selected' : '' }}>Mês</option>
                                <option value="year"  {{ $period=='year'  ? 'selected' : '' }}>Ano</option>
                                <option value="custom"{{ $period=='custom'? 'selected' : '' }}>Intervalo</option>
                            </select>
                        </div>

                        {{-- Ano --}}
                        <div class="form-group period-block period-month period-year" style="margin-right:8px; display:none;">
                            <label for="year">Ano:&nbsp;</label>
                            <select name="year" id="year" class="form-control">
                                <option value="">—</option>
                                @foreach($tvde_years as $y)
                                    <option value="{{ $y }}" {{ (string)$year === (string)$y ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Mês --}}
                        <div class="form-group period-block period-month" style="margin-right:8px; display:none;">
                            <label for="month">Mês:&nbsp;</label>
                            <select name="month" id="month" class="form-control">
                                <option value="">—</option>
                                @foreach($tvde_months as $m)
                                    <option value="{{ $m }}" {{ (string)$month === (string)$m ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::create()->month($m)->locale('pt_PT')->translatedFormat('F') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Semanas (multi) --}}
                        <div class="form-group period-block period-week" style="margin-right:8px; display:none; min-width:280px;">
                            <label for="weeks">Semanas:&nbsp;</label>
                            <select name="weeks[]" id="weeks" class="form-control" multiple size="4" style="min-width:260px;">
                                @foreach($tvde_weeks as $w)
                                    <option value="{{ $w->id }}"
                                        {{ collect(request('weeks', []))->contains($w->id) ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::parse($w->start_date)->format('d/m/Y') }} —
                                        {{ \Carbon\Carbon::parse($w->end_date)->format('d/m/Y') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Intervalo de datas --}}
                        <div class="form-group period-block period-custom" style="margin-right:8px; display:none;">
                            <label for="start_date">De:&nbsp;</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date') }}">
                        </div>
                        <div class="form-group period-block period-custom" style="margin-right:8px; display:none;">
                            <label for="end_date">a:&nbsp;</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date') }}">
                        </div>

                        {{-- Agrupar por --}}
                        <div class="form-group" style="margin-right:8px;">
                            <label for="group_by">Agrupar por:&nbsp;</label>
                            <select name="group_by" id="group_by" class="form-control">
                                <option value="week"  {{ $groupBy=='week'  ? 'selected' : '' }}>Semana</option>
                                <option value="month" {{ $groupBy=='month' ? 'selected' : '' }}>Mês</option>
                                <option value="year"  {{ $groupBy=='year'  ? 'selected' : '' }}>Ano</option>
                            </select>
                        </div>

                        <button class="btn btn-primary" style="margin-left:8px;">Aplicar</button>
                    </form>

                    {{-- Tabs das viaturas --}}
                    <ul class="nav nav-tabs" style="margin-top:15px;">
                        @foreach ($vehicle_items as $vi)
                            <li role="presentation" {{ $vehicle_item_id == $vi->id ? 'class=active' : '' }}>
                                <a href="/admin/vehicle-profitabilities/set-vehicle-item-id/{{ $vi->id }}">
                                    {{ $vi->license_plate }}
                                    {{ $vi->driver ? '(' . $vi->driver->name . ')' : '' }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Cartões de totais --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">Resultados</div>
                        <div class="panel-body">
                            <table width="100%"><tbody>
                                <tr>
                                    <th><h1><small>Tesouraria:&nbsp;</small></h1></th>
                                    <td><h1>{{ number_format($totals['treasury'] ?? 0, 2, ',', '.') }}<small>€</small></h1></td>
                                </tr>
                                <tr>
                                    <th><h1><small>Impostos:&nbsp;</small></h1></th>
                                    <td><h1>{{ number_format($totals['taxes'] ?? 0, 2, ',', '.') }}<small>€</small></h1></td>
                                </tr>
                            </tbody></table>
                        </div>
                    </div>
                </div>

                @php $final = $totals['final'] ?? 0; @endphp
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">{{ $final >= 0 ? 'Lucro' : 'Prejuízo' }}</div>
                        <div class="panel-body">
                            <h1 style="font-size:50px;">{{ number_format($final, 2, ',', '.') }}<small>€</small></h1>
                        </div>
                    </div>
                </div>

                {{-- Gráfico --}}
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">Gráfico</div>
                        <div class="panel-body">
                            <canvas id="rentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABELA POR GRUPOS --}}
            <div class="panel panel-default">
                <div class="panel-heading">
                    @if($groupBy === 'year') Totais por Ano
                    @elseif($groupBy === 'month') Totais por Mês
                    @else Totais por Semana
                    @endif
                </div>
                <div class="panel-body table-responsive">
                    <table class="table table-bordered table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Período</th>
                                <th>Tesouraria</th>
                                <th>Impostos</th>
                                <th>Final</th>
                                <th>Semanas</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($groups as $label => $g)
                            <tr>
                                <td>
                                    @if($groupBy === 'year')
                                        {{ $label }}
                                    @elseif($groupBy === 'month')
                                        @php
                                            [$yy,$mm] = explode('-', $label);
                                            $labelText = \Carbon\Carbon::create($yy, $mm, 1)->locale('pt_PT')->translatedFormat('F \\d\\e Y');
                                        @endphp
                                        {{ $labelText }}
                                    @else
                                        @php
                                            $w = $g['weeks'][0]['week'];
                                        @endphp
                                        {{ \Carbon\Carbon::parse($w->start_date)->format('d/m') }} a {{ \Carbon\Carbon::parse($w->end_date)->format('d/m') }}
                                    @endif
                                </td>
                                <td>{{ number_format($g['treasury'], 2, ',', '.') }} €</td>
                                <td>{{ number_format($g['taxes'], 2, ',', '.') }} €</td>
                                <td><strong>{{ number_format($g['final'], 2, ',', '.') }} €</strong></td>
                                <td>
                                    {{-- Lista resumida de semanas do grupo --}}
                                    @if(!empty($g['weeks']))
                                        <details>
                                            <summary>ver semanas</summary>
                                            <ul style="margin:6px 0 0 18px;">
                                                @foreach($g['weeks'] as $row)
                                                    <li>
                                                        {{ \Carbon\Carbon::parse($row['week']->start_date)->format('d/m') }}
                                                        —
                                                        {{ \Carbon\Carbon::parse($row['week']->end_date)->format('d/m') }}
                                                        (Final: {{ number_format($row['final_total'], 2, ',', '.') }} €)
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Sem resultados para os filtros selecionados.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- (Opcional) Tabela detalhada por semana (rows) --}}
            <div class="panel panel-default">
                <div class="panel-heading">Detalhe por Semana</div>
                <div class="panel-body table-responsive">
                    <table class="table table-bordered table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Semana</th>
                                <th>Motorista</th>
                                <th>Tesouraria</th>
                                <th>Impostos</th>
                                <th>Final</th>
                                <th>Transferido</th>
                                <th>Despesas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $r)
                                <tr>
                                    <td>
                                      {{ \Carbon\Carbon::parse($r['week']->start_date)->format('d/m') }}
                                      a
                                      {{ \Carbon\Carbon::parse($r['week']->end_date)->format('d/m') }}
                                    </td>
                                    <td>{{ $r['driver']->name ?? '—' }}</td>
                                    <td>{{ number_format($r['total_treasury'], 2, ',', '.') }} €</td>
                                    <td>{{ number_format($r['total_taxes'], 2, ',', '.') }} €</td>
                                    <td><strong>{{ number_format($r['final_total'], 2, ',', '.') }} €</strong></td>
                                    <td>{{ number_format($r['receipt']->amount_transferred ?? 0, 2, ',', '.') }} €</td>
                                    <td style="min-width:260px;">
                                        @php
                                            $items = $r['vehicle_expenses_items'] ?? [];
                                            $weekStart = \Carbon\Carbon::parse($r['week']->start_date)->format('Y-m-d');
                                            $weekEnd = \Carbon\Carbon::parse($r['week']->end_date)->format('Y-m-d');
                                        @endphp

                                        <div>
                                            <small>
                                                Total: <strong>{{ number_format($r['vehicle_expenses_value'] ?? 0, 2, ',', '.') }} €</strong>
                                                &nbsp;|&nbsp;
                                                IVA (contabilizado): <strong>{{ number_format($r['vehicle_expenses_vat'] ?? 0, 2, ',', '.') }} €</strong>
                                            </small>
                                        </div>

                                        <details style="margin-top:6px;">
                                            <summary>
                                                {{ count($items) > 0 ? 'ver despesas (' . count($items) . ')' : 'ver despesas' }}
                                            </summary>

                                            @if(count($items) === 0)
                                                <div style="margin-top:8px;">
                                                    <em>Sem despesas registadas (ou lista desativada para períodos longos).</em>
                                                    <div style="margin-top:6px;">
                                                        <a class="btn btn-xs btn-default" href="{{ route('admin.vehicle-expenses.index') }}">
                                                            abrir despesas
                                                        </a>
                                                    </div>
                                                </div>
                                            @else
                                                <div style="margin-top:8px;">
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-striped table-sm" style="margin-bottom:0;">
                                                            <thead>
                                                                <tr>
                                                                    <th>Data</th>
                                                                    <th>Tipo</th>
                                                                    <th>Valor</th>
                                                                    <th>Fatura</th>
                                                                    <th>IVA</th>
                                                                    <th>Tesouraria</th>
                                                                    <th>IVA (valor)</th>
                                                                    <th>Obs.</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($items as $e)
                                                                    <tr>
                                                                        <td>{{ \Carbon\Carbon::parse($e['date'])->format('d/m/Y') }}</td>
                                                                        <td>
                                                                            {{ $e['expense_type'] ?? '—' }}
                                                                            @php
                                                                                $norm = $e['effective_normalized_type'] ?? $e['normalized_type'] ?? null;
                                                                            @endphp
                                                                            @if(!empty($norm))
                                                                                <br><small style="color:#666;">({{ $norm }})</small>
                                                                            @endif
                                                                        </td>
                                                                        <td>{{ number_format($e['value'] ?? 0, 2, ',', '.') }} €</td>
                                                                        <td>
                                                                            {{ $e['invoice_value'] !== null ? number_format($e['invoice_value'], 2, ',', '.') . ' €' : '—' }}
                                                                        </td>
                                                                        <td>{{ number_format($e['vat_rate'] ?? 0, 2, ',', '.') }}%</td>
                                                                        <td>{{ number_format($e['treasury'] ?? 0, 2, ',', '.') }} €</td>
                                                                        <td>
                                                                            {{ number_format($e['vat_amount'] ?? 0, 2, ',', '.') }} €
                                                                            @if(($e['included_in_profitability'] ?? true) === false)
                                                                                <br><small style="color:#666;">(excluída do cálculo)</small>
                                                                            @elseif(($e['vat_included'] ?? true) === false)
                                                                                <br><small style="color:#666;">(IVA não conta)</small>
                                                                            @endif
                                                                        </td>
                                                                        <td style="white-space:nowrap;">
                                                                            <a class="btn btn-xs btn-primary" href="{{ route('admin.vehicle-expenses.edit', $e['id']) }}">
                                                                                editar
                                                                            </a>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div style="margin-top:6px;">
                                                        <small style="color:#666;">
                                                            Nota: despesas com tipo normalizado <code>acquisition</code> não entram no cálculo de IVA desta semana.
                                                        </small>
                                                    </div>
                                                </div>
                                            @endif
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7">Sem dados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>
<script>
    // Mostrar/ocultar blocos do formulário conforme o período
    function togglePeriodBlocks() {
        var p = document.getElementById('period').value;
        document.querySelectorAll('.period-block').forEach(el => el.style.display = 'none');
        if (p === 'week') {
            document.querySelectorAll('.period-week').forEach(el => el.style.display = 'inline-block');
        } else if (p === 'month') {
            document.querySelectorAll('.period-month').forEach(el => el.style.display = 'inline-block');
        } else if (p === 'year') {
            document.querySelectorAll('.period-year').forEach(el => el.style.display = 'inline-block');
        } else if (p === 'custom') {
            document.querySelectorAll('.period-custom').forEach(el => el.style.display = 'inline-block');
        }
    }
    document.getElementById('period').addEventListener('change', togglePeriodBlocks);
    togglePeriodBlocks(); // inicial

    // Chart data (labels from controller to keep gaps consistent)
    const chartLabels = @json($chart['labels']);
    const chartDataTreasury = @json($chart['treasury']);
    const chartDataTaxes = @json($chart['taxes']);
    const chartDataFinal = @json($chart['final']);

    const ctx = document.getElementById('rentChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Tesouraria (EUR)',
                    data: chartDataTreasury,
                    borderWidth: 1,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)'
                },
                {
                    label: 'Impostos (EUR)',
                    data: chartDataTaxes,
                    borderWidth: 1,
                    backgroundColor: 'rgba(255, 159, 64, 0.5)',
                    borderColor: 'rgba(255, 159, 64, 1)'
                },
                {
                    label: 'Final (EUR)',
                    data: chartDataFinal,
                    borderWidth: 1,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
                title: {
                    display: true,
                    text: 'Rentabilidade por ' + ({
                        'week': 'Semana',
                        'month': 'Mês',
                        'year': 'Ano'
                    })['{{ $groupBy }}']
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (v)=> v + 'EUR' }
                }
            }
        }
    });
</script>
@endsection
