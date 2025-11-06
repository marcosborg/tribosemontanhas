@extends('layouts.admin')

@section('styles')
<style>
    table { width: 100%; font-size: 14px; }
    tr { line-height: 25px; }
    tr:nth-child(even) { background-color: #eeeeee; }
    tr:nth-child(odd)  { background-color: #ffffff; }
    .btn-sm { padding: 0px 10px; font-size: 12px; line-height: 1.6; border-radius: 4px; }
    .unverified { color: #cccccc; }
    .verified   { color: #00a65a; }
    .flag-red   { color: #dc3545; margin-left: 6px; cursor: help; }
    .table-sticky-container { width: 100%; overflow-x: auto; }

    /* --- Toolbar bonita na panel-heading --- */
    .report-toolbar{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        flex-wrap:wrap;
    }
    .report-toolbar .left-actions{
        display:flex;
        gap:8px;
        align-items:center;
        flex-wrap:wrap;
    }
    .report-toolbar .search-wrap{
        margin-left:auto;
        min-width: 240px;
        max-width: 340px;
        width: 100%;
    }
    .report-search.input-group{
        width:100%;
        border:1px solid #d9e2ec;
        border-radius:6px;
        overflow:hidden;
        background:#fff;
        box-shadow:0 1px 2px rgba(16,24,40,.04), 0 1px 1px rgba(16,24,40,.02) inset;
        transition: box-shadow .15s ease, border-color .15s ease;
    }
    .report-search .form-control{
        border:0;
        height:32px;
        padding:6px 10px;
        box-shadow:none;
        font-size:13px;
    }
    .report-search .input-group-addon{
        background:#fff;
        border:0;
        font-size:13px;
        color:#6b7280;
    }
    .report-search:focus-within{
        border-color:#3b82f6;
        box-shadow:0 0 0 3px rgba(59,130,246,.15);
    }

    /* Botões compactos e com pequenas sombras */
    .btn-primary.btn-sm, .btn-success.btn-sm{
        box-shadow: 0 1px 0 rgba(0,0,0,.04);
    }
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
        document.getElementById('unselectAll').style.display = 'inline-block';
        checkCheckedCheckboxes();
    }

    function unselectAll() {
        const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)');
        checkboxes.forEach((c) => c.checked = false);
        document.getElementById('selectAll').style.display   = 'inline-block';
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

<script>
$(document).on('click', '.flag-toggle', function(e){
    e.preventDefault();
    var target = $(this).data('target');

    // Fecha outras abertas e alterna a clicada
    $('.diff-row').not(target).hide();
    $(target).toggle();
});
</script>

{{-- ====== PESQUISA GLOBAL CLIENT-SIDE ====== --}}
<script>
(function () {
  function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(this,a), ms); }; }
  function normalizeText(s){
    if (s == null) return '';
    return (''+s).replace(/\s+/g,' ').trim()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/[€]/g,'').toLowerCase();
  }

  function applyFilter(){
    const q = normalizeText($('#globalFilter').val() || '');
    const $table = $('.panel table.table').first();
    const $rows  = $table.find('tbody > tr').not('.diff-row');

    if (!q) { $rows.show(); return; }

    $rows.each(function(){
      const $tr = $(this);
      const rowText = normalizeText($tr.text());
      const show = rowText.includes(q);
      $tr.toggle(show);

      if (!show) {
        const $next = $tr.next('.diff-row');
        if ($next.length) $next.hide();
      }
    });
  }

  $(document).on('input', '#globalFilter', debounce(applyFilter, 120));
})();
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
            <div class="report-toolbar">
                <div class="left-actions">
                    <strong>Faturação</strong>
                    <button class="btn btn-primary btn-sm" onclick="selectAll()" id="selectAll">Selecionar todos</button>
                    <button class="btn btn-primary btn-sm" onclick="unselectAll()" id="unselectAll" style="display:none;">Remover seleção</button>
                    <button class="btn btn-success btn-sm" onclick="validateData()" id="validateData" disabled>Validar selecionados</button>
                </div>

                <div class="search-wrap">
                    <div class="input-group report-search">
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-search" aria-hidden="true"></span>
                        </span>
                        <input id="globalFilter" type="text" class="form-control" placeholder="Pesquisar...">
                    </div>
                </div>
            </div>
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
                        <th style="text-align:right;">Último saldo</th>
                        <th style="text-align:right;">Saldo atual</th>
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

                            $ajustes = $driver->earnings['adjustments_array'] ?? [];
                            $ajustesList = collect($ajustes)->map(function($a){
                                $type  = is_array($a) ? ($a['type'] ?? '') : ($a->type ?? '');
                                $name  = is_array($a) ? ($a['name'] ?? 'Ajuste') : ($a->name ?? 'Ajuste');
                                $amount= (float) (is_array($a) ? ($a['amount'] ?? 0) : ($a->amount ?? 0));
                                $notes = is_array($a) ? ($a['notes'] ?? '') : ($a->notes ?? '');
                                $sd    = is_array($a) ? ($a['start_date'] ?? '') : ($a->start_date ?? '');
                                $ed    = is_array($a) ? ($a['end_date'] ?? '') : ($a->end_date ?? '');
                                $sign  = $type === 'deduct' ? '-' : '+';

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
                                       data-content="{!! $ajustesList !!}"><span class="glyphicon glyphicon-eye-open"></span></a>
                                @endif
                            </td>
                            {{-- ============================================================ --}}

                            <td style="text-align:right;">{{ number_format($driver->earnings['car_track'] ?? 0, 2) }} <small>€</small></td>
                            <td style="text-align:right;">-{{ number_format($driver->earnings['car_hire'] ?? 0, 2) }} <small>€</small></td>
                            <td style="text-align:right;">{{ number_format($driver->drivers_balance ?? 0, 2) }} <small>€</small></td>
                            <td style="text-align:right;">{{ number_format($driver->balance ?? 0, 2) }} <small>€</small></td>

                            {{-- Valor da semana + red flag (clicável) --}}
                            <td style="text-align:right;">
                                {{ number_format($valorSemanaAtual, 2) }} <small>€</small>

                                @if($temDif)
                                    <a href="#"
                                    class="flag-toggle flag-red"
                                    data-target="#diff-{{ $driver->id }}"
                                    title="Valor validado: {{ number_format($valorSemanaValidado, 2) }}€ | Diferença: {{ number_format($diff, 2) }}€">
                                        🚩
                                    </a>
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
                        @php
                            $cca = $driver->current_account_data ?? null;

                            // Valores ATUAIS (lado esquerdo)
                            $cur_uber_net  = (float) ($driver->earnings['uber']['uber_net']  ?? 0);
                            $cur_bolt_net  = (float) ($driver->earnings['bolt']['bolt_net']  ?? 0);
                            $cur_vat       = (float) ($driver->earnings['vat_value']        ?? 0);
                            $cur_fuel      = (float) ($driver->fuel                         ?? 0);
                            $cur_adj       = (float) ($driver->adjustments                  ?? 0);
                            $cur_cartrack  = (float) ($driver->earnings['car_track']        ?? 0);
                            $cur_carhire   = (float) ($driver->earnings['car_hire']         ?? 0);
                            $cur_total     = (float) ($valorSemanaAtual);

                            // Valores VALIDADOS (lado direito) vindos do CurrentAccount->data
                            $val_uber_net  = (float) data_get($cca, 'uber.uber_net', 0);
                            $val_bolt_net  = (float) data_get($cca, 'bolt.bolt_net', 0);
                            $val_vat       = (float) data_get($cca, 'vat_value', 0);
                            $val_fuel      = (float) data_get($cca, 'fuel_transactions', 0);
                            $val_adj       = (float) data_get($cca, 'adjustments', 0);
                            $val_cartrack  = (float) data_get($cca, 'car_track', 0);
                            $val_carhire   = (float) data_get($cca, 'car_hire', 0);
                            $val_total     = (float) data_get($cca, 'total', 0);

                            $fmt = fn($n) => number_format((float)$n, 2) . ' €';
                        @endphp

                        <tr id="diff-{{ $driver->id }}" class="diff-row" style="display:none; background:#fff7f7;">
                            <td colspan="16" style="padding:12px 16px;">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <h5><strong>Atual</strong></h5>
                                        <table class="table table-condensed" style="margin-bottom:0;">
                                            <tr><td>Uber líquido</td>    <td style="text-align:right;">{{ $fmt($cur_uber_net) }}</td></tr>
                                            <tr><td>Bolt líquido</td>    <td style="text-align:right;">{{ $fmt($cur_bolt_net) }}</td></tr>
                                            <tr><td>IVA</td>             <td style="text-align:right;">{{ $fmt($cur_vat) }}</td></tr>
                                            <tr><td>Abastecimento</td>   <td style="text-align:right;">{{ $fmt($cur_fuel) }}</td></tr>
                                            <tr><td>Ajustes</td>         <td style="text-align:right;">{{ $fmt($cur_adj) }}</td></tr>
                                            <tr><td>Via Verde</td>       <td style="text-align:right;">{{ $fmt($cur_cartrack) }}</td></tr>
                                            <tr><td>Aluguer</td>         <td style="text-align:right;">{{ $fmt($cur_carhire) }}</td></tr>
                                            <tr><td><strong>Total semana</strong></td>
                                                <td style="text-align:right;"><strong>{{ $fmt($cur_total) }}</strong></td></tr>
                                        </table>
                                    </div>
                                    <div class="col-sm-6">
                                        <h5><strong>Validado</strong></h5>
                                        <table class="table table-condensed" style="margin-bottom:0;">
                                            <tr><td>Uber líquido</td>    <td style="text-align:right;">{{ $fmt($val_uber_net) }}</td></tr>
                                            <tr><td>Bolt líquido</td>    <td style="text-align:right;">{{ $fmt($val_bolt_net) }}</td></tr>
                                            <tr><td>IVA</td>             <td style="text-align:right;">{{ $fmt($val_vat) }}</td></tr>
                                            <tr><td>Abastecimento</td>   <td style="text-align:right;">{{ $fmt($val_fuel) }}</td></tr>
                                            <tr><td>Ajustes</td>         <td style="text-align:right;">{{ $fmt($val_adj) }}</td></tr>
                                            <tr><td>Via Verde</td>       <td style="text-align:right;">{{ $fmt($val_cartrack) }}</td></tr>
                                            <tr><td>Aluguer</td>         <td style="text-align:right;">{{ $fmt($val_carhire) }}</td></tr>
                                            <tr><td><strong>Total semana</strong></td>
                                                <td style="text-align:right;"><strong>{{ $fmt($val_total) }}</strong></td></tr>
                                        </table>
                                    </div>
                                </div>

                                <hr style="margin:8px 0;">
                                <div class="row">
                                    <div class="col-sm-12" style="font-size:13px;">
                                        <strong>Diferenças (Atual - Validado):</strong>
                                        <ul style="margin:6px 0 0 18px;">
                                            <li>Uber líquido: {{ $fmt($cur_uber_net - $val_uber_net) }}</li>
                                            <li>Bolt líquido: {{ $fmt($cur_bolt_net - $val_bolt_net) }}</li>
                                            <li>IVA: {{ $fmt($cur_vat - $val_vat) }}</li>
                                            <li>Abastecimento: {{ $fmt($cur_fuel - $val_fuel) }}</li>
                                            <li>Ajustes: {{ $fmt($cur_adj - $val_adj) }}</li>
                                            <li>Via Verde: {{ $fmt($cur_cartrack - $val_cartrack) }}</li>
                                            <li>Aluguer: {{ $fmt($cur_carhire - $val_carhire) }}</li>
                                            <li><strong>Total semana:</strong> {{ $fmt($cur_total - $val_total) }}</li>
                                        </ul>
                                    </div>
                                </div>
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
