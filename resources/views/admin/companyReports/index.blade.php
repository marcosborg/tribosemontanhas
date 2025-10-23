@extends('layouts.admin')

@section('styles')
<style>
    table { width: 100%; font-size: 14px; }
    tr { line-height: 25px; }
    tr:nth-child(even) { background-color: #eeeeee; }
    tr:nth-child(odd)  { background-color: #ffffff; }
    .btn-sm { padding: 0px 5px; font-size: 12px; line-height: 1.5; border-radius: 3px; margin-left: 10px; }
    .unverified { color: #cccccc; }
    .verified   { color: #00a65a; }
    .flag-red   { color: #dc3545; margin-left: 6px; cursor: help; }
    .table-sticky-container { width: 100%; overflow-x: auto; }
</style>
@endsection

@section('scripts')
@parent
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    validateData = () => {
        const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)');
        const data = [];
        checkboxes.forEach((checkbox) => {
            let driver = JSON.parse(checkbox.value);
            data.push({
                driver: driver,
                tvde_week_id: {{ session()->get('tvde_week_id') }}
            });
        });
        $.post({
            url: '/admin/company-reports/validate-data',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { data: data },
            success: () => {
                Swal.fire('Atualizado com sucesso').then(() => location.reload());
            },
            error: (error) => console.log(error)
        });
    }

    function selectAll() {
        const checkboxes = document.querySelectorAll('input[type="checkbox"]:not(:checked):not(:disabled)');
        checkboxes.forEach((c) => c.checked = true);
        document.getElementById('selectAll').style.display   = 'none';
        document.getElementById('unselectAll').style.display = 'block';
        checkCheckedCheckboxes();
    }

    function unselectAll() {
        const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)');
        checkboxes.forEach((c) => c.checked = false);
        document.getElementById('selectAll').style.display   = 'block';
        document.getElementById('unselectAll').style.display = 'none';
        checkCheckedCheckboxes();
    }

    function checkCheckedCheckboxes() {
        const checked = document.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)');
        document.getElementById('validateData').disabled = checked.length === 0;
    }

    document.addEventListener('change', function(e){
        if (e.target && e.target.matches('input[type="checkbox"]')) {
            checkCheckedCheckboxes();
        }
    });

    // Inicializa popovers dos ícones de ajustes
    $(function() { $('[data-toggle="popover"]').popover() })

    function deleteData(tvde_week_id, driver_id) {
        Swal.fire({
            title: 'Tem a certeza?',
            text: "Isto irá remover os dados do extrato deste condutor para esta semana.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.get(`/admin/company-reports/delete-data/${tvde_week_id}/${driver_id}`, function() {
                    Swal.fire('Removido!','Os dados foram removidos com sucesso.','success')
                        .then(() => location.reload());
                }).fail(function() {
                    Swal.fire('Erro!','Ocorreu um erro ao remover os dados.','error');
                });
            }
        });
    }
</script>
@endsection

@section('content')
<div class="content">
    @if ($company_id == 0)
        <div class="alert alert-info" role="alert">
            Selecione uma empresa para ver os extratos.
        </div>
    @else

    {{-- Botões de navegação --}}
    <div class="btn-group btn-group-justified" role="group">
        @foreach ($tvde_years as $tvde_year)
            <a href="/admin/financial-statements/year/{{ $tvde_year->id }}"
               class="btn btn-default {{ $tvde_year->id == $tvde_year_id ? 'disabled selected' : '' }}">
               {{ $tvde_year->name }}
            </a>
        @endforeach
    </div>

    <div class="btn-group btn-group-justified" role="group" style="margin-top: 5px;">
        @foreach ($tvde_months as $tvde_month)
            <a href="/admin/financial-statements/month/{{ $tvde_month->id }}"
               class="btn btn-default {{ $tvde_month->id == $tvde_month_id ? 'disabled selected' : '' }}">
               {{ $tvde_month->name }}
            </a>
        @endforeach
    </div>

    <div class="btn-group btn-group-justified" role="group" style="margin-top: 5px;">
        @foreach ($tvde_weeks as $tvde_week)
            <a href="/admin/financial-statements/week/{{ $tvde_week->id }}"
               class="btn btn-default {{ $tvde_week->id == $tvde_week_id ? 'disabled selected' : '' }}">
               Semana de {{ \Carbon\Carbon::parse($tvde_week->start_date)->format('d') }}
               a {{ \Carbon\Carbon::parse($tvde_week->end_date)->format('d') }}
            </a>
        @endforeach
    </div>

    {{-- Tabela --}}
    <div class="panel panel-default" style="margin-top: 20px;">
        <div class="panel-heading">
            Faturação
            <button class="btn btn-success btn-sm pull-right" onclick="validateData()" id="validateData" disabled>Validar selecionados</button>
            <button class="btn btn-primary btn-sm pull-right" onclick="selectAll()" id="selectAll">Selecionar todos</button>
            <button class="btn btn-primary btn-sm pull-right" onclick="unselectAll()" id="unselectAll" style="display: none;">Remover seleção</button>
        </div>

        <div class="table-sticky-container">
            <table class="table table-bordered table-striped table-sm">
                <thead>
                    <tr>
                        <th>Condutor</th>
                        <th style="text-align:right; display:none;">Bruto Uber</th>
                        <th style="text-align:right; display:none;">Bruto Bolt</th>
                        <th style="text-align:right; display:none;">Bruto operadores</th>
                        <th style="text-align:right;">Líquido Uber</th>
                        <th style="text-align:right;">Líquido Bolt</th>
                        <th style="text-align:right;">IVA</th>
                        <th style="text-align:right;">Abastecimento</th>
                        <th style="text-align:right;">Ajustes</th>
                        <th style="text-align:right;">Via verde</th>
                        <th style="text-align:right;">Aluguer</th>
                        <th style="text-align:right;">Saldo</th>
                        <th style="text-align:right;">Valor da semana</th>
                        <th style="text-align:right;">A pagar</th>
                        <th style="text-align:right;">Validar</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                @foreach ($drivers as $driver)
                    @if ($driver->earnings)
                        @php
                            $valorSemanaAtual    = (float) ($driver->final_total ?? $driver->total ?? 0);
                            $valorSemanaValidado = (float) data_get($driver, 'current_account_data.total', 0);
                            $temCCA = (bool) ($driver->current_account ?? false);
                            $diff   = round($valorSemanaAtual - $valorSemanaValidado, 2);
                            $temDif = $temCCA && abs($diff) >= 0.01;

                            // Array/collection de ajustes (pode vir como array associativo/objetos)
                            $ajustes = $driver->earnings['adjustments_array'] ?? [];
                            $ajustesList = collect($ajustes)->map(function($a){
                                // $a pode ser array ou objeto
                                $type  = is_array($a) ? ($a['type'] ?? '') : ($a->type ?? '');
                                $name  = is_array($a) ? ($a['name'] ?? 'Ajuste') : ($a->name ?? 'Ajuste');
                                $amount= (float) (is_array($a) ? ($a['amount'] ?? 0) : ($a->amount ?? 0));
                                $notes = is_array($a) ? ($a['notes'] ?? '') : ($a->notes ?? '');
                                $sd    = is_array($a) ? ($a['start_date'] ?? '') : ($a->start_date ?? '');
                                $ed    = is_array($a) ? ($a['end_date'] ?? '') : ($a->end_date ?? '');
                                $sign  = $type === 'deduct' ? '-' : '+';

                                // Escapes
                                $nameEsc  = e($name);
                                $notesEsc = e($notes);
                                $sdEsc    = e($sd);
                                $edEsc    = e($ed);

                                $dates = trim(($sdEsc || $edEsc) ? "{$sdEsc} a {$edEsc}" : '');

                                return "<div style='margin-bottom:6px;'>
                                            <strong>{$nameEsc}</strong>" . ($dates ? " <small>({$dates})</small>" : "") . "<br>
                                            {$sign}" . number_format($amount, 2) . "€
                                            " . ($notesEsc ? "<br><em>{$notesEsc}</em>" : "") . "
                                        </div>";
                            })->implode('');
                        @endphp

                        <tr>
                            <td>{{ $driver->name }}</td>

                            <td style="text-align:right;">{{ number_format($driver->earnings['uber']['uber_net'] ?? 0, 2) }} <small>€</small></td>
                            <td style="text-align:right;">{{ number_format($driver->earnings['bolt']['bolt_net'] ?? 0, 2) }} <small>€</small></td>

                            <td style="text-align:right; color:red;">- {{ number_format($driver->earnings['vat_value'] ?? 0, 2) }} <small>€</small></td>
                            <td style="text-align:right;">-{{ number_format($driver->fuel ?? 0, 2) }} <small>€</small></td>

                            {{-- ================= AJUSTES com ícone/Popover ================= --}}
                            <td style="text-align:right;">
                                {{ number_format($driver->adjustments ?? 0, 2) }} <small>€</small>
                                @if(!empty($ajustes) && count($ajustes))
                                    <a tabindex="0"
                                       class="flag-red"
                                       role="button"
                                       data-toggle="popover"
                                       data-trigger="focus"
                                       data-html="true"
                                       title="Ajustes"
                                       data-content="{!! $ajustesList !!}">
                                        <span class="glyphicon glyphicon-eye-open"></span>
                                    </a>
                                @endif
                            </td>
                            {{-- ============================================================ --}}

                            <td style="text-align:right;">{{ number_format($driver->earnings['car_track'] ?? 0, 2) }} <small>€</small></td>
                            <td style="text-align:right;">-{{ number_format($driver->earnings['car_hire'] ?? 0, 2) }} <small>€</small></td>
                            <td style="text-align:right;">{{ number_format($driver->balance ?? 0, 2) }} <small>€</small></td>

                            {{-- Valor da semana + red flag --}}
                            <td style="text-align:right;">
                                {{ number_format($valorSemanaAtual, 2) }} <small>€</small>
                                @if($temDif)
                                    <span class="flag-red"
                                        title="Valor validado: {{ number_format($valorSemanaValidado, 2) }}€ | Diferença: {{ number_format($diff, 2) }}€">🚩</span>
                                @endif
                            </td>

                            {{-- A pagar --}}
                            <td style="text-align:right;">
                                {{ number_format($driver->final_total_balance ?? 0, 2) }} <small>€</small>
                            </td>

                            <td style="text-align:right">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" value="{{ json_encode($driver) }}" {{ $driver->current_account ? 'checked disabled' : '' }}>
                                        <span class="glyphicon glyphicon-ok green-checkmark {{ $driver->current_account ? 'verified' : 'unverified' }}"></span>
                                    </label>
                                </div>
                            </td>

                            <td>
                                <button type="button" onclick="deleteData({{ $tvde_week_id }}, {{ $driver->id }})" class="btn btn-sm">
                                    <span class="glyphicon glyphicon-trash"></span>
                                </button>
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <th>Totais</th>
                        <th style="text-align:right;">{{ number_format($totals['net_uber'] ?? 0, 2) }} <small>€</small></th>
                        <th style="text-align:right;">{{ number_format($totals['net_bolt'] ?? 0, 2) }} <small>€</small></th>
                        <th style="text-align:right; color:red;">- {{ number_format($totals['total_vat_value'] ?? 0, 2) }} <small>€</small></th>
                        <th style="text-align:right;">-{{ number_format($totals['total_fuel_transactions'] ?? 0, 2) }} <small>€</small></th>
                        <th style="text-align:right;">{{ number_format($totals['total_adjustments'] ?? 0, 2) }} <small>€</small></th>
                        <th style="text-align:right;">{{ number_format($totals['total_car_track'] ?? 0, 2) }} <small>€</small></th>
                        <th style="text-align:right;">-{{ number_format($totals['total_car_hire'] ?? 0, 2) }} <small>€</small></th>
                        <th></th>
                        <th style="text-align:right;">{{ number_format($totals['total_drivers'] ?? 0, 2) }} <small>€</small></th>
                        <th></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @endif
</div>
@endsection
