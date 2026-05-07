@extends('layouts.admin')
@section('content')
<div class="content">
    @can('vehicle_expense_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.vehicle-expenses.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.vehicleExpense.title_singular') }}
                </a>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                    {{ trans('global.app_csvImport') }}
                </button>
                @include('csvImport.modal', ['model' => 'VehicleExpense', 'route' => 'admin.vehicle-expenses.parseCsvImport'])
            </div>
        </div>
    @endcan
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.vehicleExpense.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    <div class="alert alert-warning" style="margin-bottom: 15px;">
                        Despesas por pagar: <strong>{{ $unpaidCount ?? 0 }}</strong>
                    </div>
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-3">
                            <label for="date_from_filter">Data inicial</label>
                            <input type="text" id="date_from_filter" class="form-control date" value="">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to_filter">Data final</label>
                            <input type="text" id="date_to_filter" class="form-control date" value="">
                        </div>
                        <div class="col-md-3" style="padding-top: 25px;">
                            <button type="button" id="apply_date_range_filter" class="btn btn-primary">
                                Filtrar datas
                            </button>
                            <button type="button" id="reset_date_range_filter" class="btn btn-default">
                                Limpar
                            </button>
                        </div>
                    </div>

                    <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-VehicleExpense">
                        <thead>
                            <tr>
                                <th width="10"></th>
                                <th>{{ trans('cruds.vehicleExpense.fields.id') }}</th>
                                <th>{{ trans('cruds.vehicleExpense.fields.vehicle_item') }}</th>
                                <th>{{ trans('cruds.vehicleExpense.fields.expense_type') }}</th>
                                <th>{{ trans('cruds.vehicleExpense.fields.date') }}</th>
                                <th>Estado</th>
                                <th>Pago em</th>
                                <th>{{ trans('cruds.vehicleExpense.fields.files') }}</th>
                                <th>{{ trans('cruds.vehicleExpense.fields.value') }}</th>
                                <th>{{ trans('cruds.vehicleExpense.fields.vat') }}</th>
                                <th>Valor final</th>
                                <th>Grupo</th>
                                <th>Pagar a</th>
                                <th>&nbsp;</th>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <input class="search" type="text" placeholder="{{ trans('global.search') }}">
                                </td>
                                <td>
                                    <select class="search">
                                        <option value>{{ trans('global.all') }}</option>
                                        @foreach($vehicle_items as $key => $item)
                                            <option value="{{ $item->license_plate }}">{{ $item->license_plate }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="search" strict="true">
                                        <option value>{{ trans('global.all') }}</option>
                                        @foreach(App\Models\VehicleExpense::EXPENSE_TYPE_RADIO as $key => $item)
                                            <option value="{{ $key }}">{{ $item }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td></td>
                                <td>
                                    <select class="search" strict="true">
                                        <option value>{{ trans('global.all') }}</option>
                                        <option value="1">Pago</option>
                                        <option value="0">Por pagar</option>
                                    </select>
                                </td>
                                <td></td>
                                <td></td>
                                <td>
                                    <input class="search" type="text" placeholder="{{ trans('global.search') }}">
                                </td>
                                <td>
                                    <input class="search" type="text" placeholder="{{ trans('global.search') }}">
                                </td>
                                <td></td>
                                <td>
                                    <input class="search" type="text" placeholder="{{ trans('global.search') }}">
                                </td>
                                <td>
                                    <input class="search" type="text" placeholder="{{ trans('global.search') }}">
                                </td>
                                <td></td>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
@parent
<style>
    .datatable-VehicleExpense tbody tr.is-clickable {
        cursor: pointer;
    }
</style>
<script>
    $(function () {
        let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)
        @can('vehicle_expense_delete')
        let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
        let deleteButton = {
            text: deleteButtonTrans,
            url: "{{ route('admin.vehicle-expenses.massDestroy') }}",
            className: 'btn-danger',
            action: function (e, dt, node, config) {
                var ids = $.map(dt.rows({ selected: true }).data(), function (entry) {
                    return entry.id
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
                }
            }
        }
        dtButtons.push(deleteButton)
        @endcan

        let dtOverrideGlobals = {
            buttons: dtButtons,
            processing: true,
            serverSide: true,
            retrieve: true,
            stateSave: true,
            stateSaveCallback: function (settings, data) {
                data.date_from = $('#date_from_filter').val();
                data.date_to = $('#date_to_filter').val();
                localStorage.setItem('datatable-vehicle-expenses-v5', JSON.stringify(data));
            },
            stateLoadCallback: function (settings) {
                return JSON.parse(localStorage.getItem('datatable-vehicle-expenses-v5'));
            },
            aaSorting: [],
            ajax: {
                url: "{{ route('admin.vehicle-expenses.index') }}",
                data: function (d) {
                    d.date_from = $('#date_from_filter').val();
                    d.date_to = $('#date_to_filter').val();
                }
            },
            columns: [
                { data: 'placeholder', name: 'placeholder' },
                { data: 'id', name: 'id' },
                { data: 'vehicle_item_license_plate', name: 'vehicle_item.license_plate' },
                { data: 'expense_type', name: 'expense_type' },
                { data: 'date', name: 'date' },
                { data: 'paid_status', name: 'is_paid' },
                { data: 'paid_at', name: 'paid_at' },
                { data: 'files', name: 'files', sortable: false, searchable: false },
                { data: 'value', name: 'value' },
                { data: 'vat', name: 'vat' },
                { data: 'final_value', name: 'final_value', sortable: false, searchable: false },
                { data: 'group_info', name: 'group_label' },
                { data: 'pay_to', name: 'pay_to' },
                { data: 'actions', name: '{{ trans('global.actions') }}' }
            ],
            createdRow: function (row, data) {
                @can('vehicle_expense_edit')
                $(row)
                    .addClass('is-clickable')
                    .attr('data-edit-url', "{{ route('admin.vehicle-expenses.edit', '__ID__') }}".replace('__ID__', data.id));
                @endcan
            },
            orderCellsTop: true,
            order: [[1, 'desc']],
            pageLength: 100,
        };
        let table = $('.datatable-VehicleExpense').DataTable(dtOverrideGlobals);

        const savedState = JSON.parse(localStorage.getItem('datatable-vehicle-expenses-v5') || 'null');
        if (savedState && savedState.date_from) {
            $('#date_from_filter').val(savedState.date_from);
        }
        if (savedState && savedState.date_to) {
            $('#date_to_filter').val(savedState.date_to);
        }

        $('a[data-toggle="tab"]').on('shown.bs.tab click', function () {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
        });

        $('#apply_date_range_filter').on('click', function () {
            const state = JSON.parse(localStorage.getItem('datatable-vehicle-expenses-v5') || '{}');
            state.date_from = $('#date_from_filter').val();
            state.date_to = $('#date_to_filter').val();
            localStorage.setItem('datatable-vehicle-expenses-v5', JSON.stringify(state));
            table.draw();
        });

        $('#reset_date_range_filter').on('click', function () {
            $('#date_from_filter').val('');
            $('#date_to_filter').val('');
            const state = JSON.parse(localStorage.getItem('datatable-vehicle-expenses-v5') || '{}');
            delete state.date_from;
            delete state.date_to;
            localStorage.setItem('datatable-vehicle-expenses-v5', JSON.stringify(state));
            table.draw();
        });

        let visibleColumnsIndexes = null;
        $('.datatable thead').on('input change', '.search', function () {
            let strict = $(this).attr('strict') || false
            let value = strict && this.value ? "^" + this.value + "$" : this.value

            let index = $(this).parent().index()
            if (visibleColumnsIndexes !== null) {
                index = visibleColumnsIndexes[index]
            }

            table
                .column(index)
                .search(value, strict)
                .draw()
        });

        table.on('column-visibility.dt', function () {
            visibleColumnsIndexes = []
            table.columns(":visible").every(function (colIdx) {
                visibleColumnsIndexes.push(colIdx);
            });
        });

        $('.datatable-VehicleExpense tbody').on('click', 'tr.is-clickable', function (event) {
            if ($(event.target).closest('a, button, form, input, select, label, textarea').length) {
                return;
            }

            const editUrl = $(this).attr('data-edit-url');
            if (editUrl) {
                window.location = editUrl;
            }
        });
    });
</script>
@endsection
