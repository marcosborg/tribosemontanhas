@extends('layouts.admin')
@section('styles')
<style>
    table {
        width: 100%;
    }

    tr {
        line-height: 25px;
    }

    tr:nth-child(even) {
        background-color: #eeeeee;
    }

    tr:nth-child(odd) {
        background-color: #ffffff;
    }

    .btn-sm {
        padding: 0px 5px;
        font-size: 12px;
        line-height: 1.5;
        border-radius: 3px;
        margin-left: 10px;
    }

    .unverified {
        color: #cccccc;
    }

    .verified {
        color: #00a65a;
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
                tvde_week_id: {{ session()->get('tvde_week_id') }},
            });
        });
        $.post({
            url: '/admin/company-reports/validate-data',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                data: data,
            },
            success: () => {
                Swal.fire('Atualizado com sucesso').then(() => {
                    location.reload();
                });
            },
            error: (error) => {
                console.log(error);
            }
        });
    }

    revalidateData = (driver_id, tvde_week_id) => {
        let data = {
            driver_id: driver_id,
            tvde_week_id: tvde_week_id
        };
        $.post({
            url: '/admin/company-reports/revalidate-data',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: data,
            success: (resp) => {
                Swal.fire('Atualizado com sucesso').then(() => {
                    location.reload();
                });
            },
            error: (error) => {
                console.log(error);
                location.reload();
            }
        });
    }


    // Função para selecionar todos os checkboxes que não estão marcados e não estão desativados
    function selectAll() {
        const checkboxes = document.querySelectorAll('input[type="checkbox"]:not(:checked):not(:disabled)');
        checkboxes.forEach((checkbox) => {
            checkbox.checked = true;
        });

        document.getElementById('selectAll').style.display = 'none';
        document.getElementById('unselectAll').style.display = 'block';
        checkCheckedCheckboxes();
    }

// Função para desmarcar todos os checkboxes que estão marcados e não estão desativados
    function unselectAll() {
        const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)');
        checkboxes.forEach((checkbox) => {
            checkbox.checked = false;
        });

        document.getElementById('selectAll').style.display = 'block';
        document.getElementById('unselectAll').style.display = 'none';
        checkCheckedCheckboxes();
    }

    // Função para verificar se existem checkboxes marcados que não estão desativados
    function checkCheckedCheckboxes() {
        const checkedCheckboxes = document.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)');
        const validateButton = document.getElementById('validateData');

        if (checkedCheckboxes.length > 0) {
            validateButton.disabled = false;
        } else {
            validateButton.disabled = true;
        }
    }

    // Adiciona um event listener para os checkboxes que chama a função checkCheckedCheckboxes() sempre que houver uma mudança
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', checkCheckedCheckboxes);
    });

</script>
@endsection
@section('content')
<div class="content">
    @if ($company_id == 0)
    <div class="alert alert-info" role="alert">
        Selecione uma empresa para ver os extratos.
    </div>
    @else
    <div class="btn-group btn-group-justified" role="group">
        @foreach ($tvde_years as $tvde_year)
        <a href="/admin/financial-statements/year/{{ $tvde_year->id }}"
            class="btn btn-default {{ $tvde_year->id == $tvde_year_id ? 'disabled selected' : '' }}">{{ $tvde_year->name
            }}</a>
        @endforeach
    </div>
    <div class="btn-group btn-group-justified" role="group" style="margin-top: 5px;">
        @foreach ($tvde_months as $tvde_month)
        <a href="/admin/financial-statements/month/{{ $tvde_month->id }}"
            class="btn btn-default {{ $tvde_month->id == $tvde_month_id ? 'disabled selected' : '' }}">{{
            $tvde_month->name
            }}</a>
        @endforeach
    </div>
    <div class="btn-group btn-group-justified" role="group" style="margin-top: 5px;">
        @foreach ($tvde_weeks as $tvde_week)
        <a href="/admin/financial-statements/week/{{ $tvde_week->id }}"
            class="btn btn-default {{ $tvde_week->id == $tvde_week_id ? 'disabled selected' : '' }}">Semana de {{
            \Carbon\Carbon::parse($tvde_week->start_date)->format('d')
            }} a {{ \Carbon\Carbon::parse($tvde_week->end_date)->format('d') }}</a>
        @endforeach
    </div>

    <div class="panel panel-default" style="margin-top: 20px;">
        <div class="panel-heading">
            Faturação
            <button class="btn btn-success btn-sm pull-right" onclick="validateData()" id="validateData"
                disabled>Validar
                selecionados</button>
            <button class="btn btn-primary btn-sm pull-right" onclick="selectAll()" id="selectAll">Selecionar
                todos</button>
            <button class="btn btn-primary btn-sm pull-right" onclick="unselectAll()" id="unselectAll"
                style="display: none;">Remover
                seleção</button>
        </div>
        <div class="panel-body">
            <table>
                <thead>
                    <tr>
                        <th>Condutor</th>
                        <th style="text-align: right;">Uber</th>
                        <th style="text-align: right;">Bolt</th>
                        <th style="text-align: right;">Operadores</th>
                        <th style="text-align: right;">Ganhos</th>
                        <th style="text-align: right;">Gorjetas</th>
                        <th style="text-align: right;">Abastecimento</th>
                        <th style="text-align: right;">Ajustes</th>
                        <th style="text-align: right;">P. frota</th>
                        <th style="text-align: right;">Saldo</th>
                        <th style="text-align: right">A pagar</th>
                        <th style="text-align: right">Validar</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($drivers as $driver)
                    @if ($driver->earnings)
                    <tr>
                        <td>{{ $driver->name }}</td>
                        <td style="text-align: right;">{{ number_format($driver->earnings['uber']['total_earnings'] ??
                            0, 2) }} <small>€</small></td>
                        <td style="text-align: right;">{{ number_format($driver->earnings['bolt']['total_earnings'] ??
                            0, 2) }} <small>€</small></td>
                        <td style="text-align: right;">{{ number_format($driver->earnings['total'] ?? 0, 2) }}
                            <small>€</small>
                        </td>
                        <td style="text-align: right;"><small>({{ $driver->earnings['percent'] ?? 0 }}%)</small> {{
                            number_format($driver->earnings['earnings_after_discount'] ??
                            0, 2) }} <small>€</small></td>
                        <td style="text-align: right;"><small>({{ $driver->earnings['tips_percent'] ?? 0 }})%</small> {{
                            number_format($driver->earnings['tips_after_discount'] ?? 0, 2) }} <small>€</small></td>
                        <td style="text-align: right;">{{ number_format($driver->fuel, 2) }}
                            <small>€</small>
                        </td>
                        <td style="text-align: right">{{ number_format($driver->adjustments, 2) }} <small>€</small></td>
                        <td style="text-align: right">{{ number_format($driver->fleet_management, 2) }} <small>€</small>
                        </td>
                        <td style="text-align: right">{{ number_format($driver->balance, 2) }} <small>€</small></td>
                        <td style="text-align: right">{{ number_format($driver->total, 2) }} <small>€</small></td>
                        <td style="text-align: right">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" value="{{ json_encode($driver) }}" {{
                                        $driver->current_account ? 'checked disabled' : '' }}><span
                                        class="glyphicon glyphicon-ok green-checkmark {{ $driver->current_account ? 'verified' : 'unverified' }}"></span>
                                </label>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            @if ($driver->current_account)
                            <button class="btn btn-sm" onclick="revalidateData({{ $driver->id }}, {{ $tvde_week_id }})">
                                <i class="fa-fw fas fa-sync-alt"></i>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>Totais</th>
                        <th style="text-align: right;">{{ number_format($totals['total_uber'], 2) }} <small>€</small>
                        </th>
                        <th style="text-align: right;">{{ number_format($totals['total_bolt'], 2) }} <small>€</small>
                        </th>
                        <th style="text-align: right;">{{ number_format($totals['total_operators'], 2) }}
                            <small>€</small>
                        </th>
                        <th style="text-align: right;">{{ number_format($totals['total_earnings_after_discount'], 2) }}
                            <small>€</small>
                        </th>
                        <th style="text-align: right;">{{ number_format($totals['total_tips_after_discount'], 2) }}
                            <small>€</small>
                        </th>
                        <th style="text-align: right;">{{ number_format($totals['total_fuel_transactions'], 2) }}
                            <small>€</small>
                        </th>
                        <th style="text-align: right;">{{ number_format($totals['total_adjustments'], 2) }}
                            <small>€</small>
                        </th>
                        <th style="text-align: right;">{{ number_format($totals['total_fleet_management'], 2) }}
                            <small>€</small>
                        </th>
                        <th style="text-align: right;">{{ number_format($totals['total_drivers'], 2) }} <small>€</small>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@endif
</div>
@endsection