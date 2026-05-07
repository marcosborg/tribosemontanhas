@extends('layouts.admin')
@section('content')
<div class="content">
    @can('driver_deposit_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.driver-deposits.create') }}">
                    Adicionar Caução
                </a>
            </div>
        </div>
    @endcan

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">Cauções - movimentos</div>
                <div class="panel-body">
                    <form method="GET" action="{{ route('admin.driver-deposits.index') }}" class="row" style="margin-bottom: 15px;">
                        <div class="col-md-3">
                            <label for="tvde_month_id">Mês</label>
                            <select class="form-control select2" name="tvde_month_id" id="tvde_month_id">
                                <option value="">Todos</option>
                                @foreach($months as $month)
                                    <option value="{{ $month->id }}" {{ (string) $monthId === (string) $month->id ? 'selected' : '' }}>{{ $month->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="driver_id">Motorista</label>
                            <select class="form-control select2" name="driver_id" id="driver_id">
                                <option value="">Todos</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" {{ (string) $driverId === (string) $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="company_id">Empresa</label>
                            <select class="form-control select2" name="company_id" id="company_id">
                                <option value="">Todas</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ (string) $companyId === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="type">Tipo</label>
                            <select class="form-control" name="type" id="type">
                                <option value="">Todos</option>
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1" style="padding-top: 25px;">
                            <button class="btn btn-primary" type="submit">Filtrar</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover datatable datatable-DriverDepositMovement">
                            <thead>
                                <tr>
                                    <th width="10"></th>
                                    <th>Motorista</th>
                                    <th>Empresa</th>
                                    <th>Semana</th>
                                    <th>Tipo</th>
                                    <th>Descrição</th>
                                    <th style="text-align:right;">Valor</th>
                                    <th style="text-align:right;">Saldo caução</th>
                                    <th>&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($movements as $movement)
                                    <tr data-entry-id="{{ $movement->id }}">
                                        <td></td>
                                        <td>{{ $movement->driver->name ?? '' }}</td>
                                        <td>{{ $movement->company->name ?? $movement->driver->company->name ?? '' }}</td>
                                        <td>{{ $movement->tvde_week->start_date ?? '' }}</td>
                                        <td>{{ \App\Models\DriverDepositMovement::TYPE_SELECT[$movement->type] ?? $movement->type }}</td>
                                        <td>{{ $movement->description }}</td>
                                        <td style="text-align:right;">{{ number_format($movement->amount, 2) }} €</td>
                                        <td style="text-align:right;">{{ number_format($movement->balance_after, 2) }} €</td>
                                        <td>
                                            @can('driver_deposit_show')
                                                <a class="btn btn-xs btn-primary" href="{{ route('admin.driver-deposits.show', $movement->driver_deposit_id) }}">Ver caução</a>
                                            @endcan
                                            @can('driver_deposit_delete')
                                                <form action="{{ route('admin.driver-deposit-movements.destroy', $movement) }}" method="POST" onsubmit="return confirm('{{ trans('global.areYouSure') }}');" style="display: inline-block;">
                                                    @method('DELETE')
                                                    @csrf
                                                    <button class="btn btn-xs btn-danger" type="submit">{{ trans('global.delete') }}</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
@parent
<script>
    $(function () {
        let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)
        @can('driver_deposit_delete')
        let deleteButton = {
            text: '{{ trans('global.datatables.delete') }}',
            url: "{{ route('admin.driver-deposit-movements.massDestroy') }}",
            className: 'btn-danger',
            action: function (e, dt, node, config) {
                var ids = $.map(dt.rows({ selected: true }).nodes(), function (entry) {
                    return $(entry).data('entry-id')
                });

                if (ids.length === 0) {
                    alert('{{ trans('global.datatables.zero_selected') }}')
                    return
                }

                if (confirm('{{ trans('global.areYouSure') }}')) {
                    $.ajax({
                        headers: {'x-csrf-token': _token},
                        method: 'POST',
                        url: config.url,
                        data: { ids: ids, _method: 'DELETE' }
                    }).done(function () { location.reload() })
                      .fail(function (jqXHR) { alert(jqXHR.responseText || 'Erro ao apagar movimentos selecionados.') })
                }
            }
        }
        dtButtons.push(deleteButton)
        @endcan

        $('.datatable-DriverDepositMovement').DataTable({
            buttons: dtButtons,
            order: [[3, 'desc']],
            pageLength: 100
        });
    });
</script>
@endsection
