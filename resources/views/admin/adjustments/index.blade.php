@extends('layouts.admin')
@section('content')
<div class="content">
    @can('adjustment_create')
    <div style="margin-bottom: 10px;" class="row">
        <div class="col-lg-6">
            <a class="btn btn-success" href="{{ route('admin.adjustments.create') }}">
                {{ trans('global.add') }} {{ trans('cruds.adjustment.title_singular') }}
            </a>
            <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                {{ trans('global.app_csvImport') }}
            </button>
            @include('csvImport.modal', ['model' => 'Adjustment', 'route' => 'admin.adjustments.parseCsvImport'])
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="required" for="driver_id">Motorista</label>
                <select class="form-control select2" name="driver_id" id="driver_id" required>
                    <option {{ request('driver_id') ? '' : 'selected' }} value="">Todos</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}" {{ request('driver_id') == $driver->id ? 'selected' : '' }}>
                            {{ $driver->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    @endcan

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.adjustment.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-Adjustment" style="width:100%">
                        <thead>
                            <tr>
                                <th width="10"></th>
                                <th>{{ trans('cruds.adjustment.fields.id') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.name') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.type') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.amount') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.percent') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.start_date') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.end_date') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.drivers') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.company') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.company_expense') }}</th>
                                <th>{{ trans('cruds.adjustment.fields.car_hire_deduct') }}</th>
                                <th>&nbsp;</th>
                            </tr>
                            {{-- Filtros por coluna --}}
                            <tr>
                                <th></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>

                                {{-- TYPE: podes escrever o rótulo ou a chave --}}
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>

                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>

                                {{-- Drivers (nome) --}}
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>

                                {{-- Empresa --}}
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>

                                {{-- checkboxes: não pesquisáveis --}}
                                <th></th>
                                <th></th>
                                <th></th>
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
<script>
$(function () {
  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)

  @can('adjustment_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.adjustments.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      const ids = $.map(dt.rows({ selected: true }).data(), function (entry) {
          return entry.id
      });
      if (ids.length === 0) { alert('{{ trans('global.datatables.zero_selected') }}'); return }
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
    aaSorting: [],
    ajax: {
      url: "{{ route('admin.adjustments.index') }}",
      data: function (d) {
        d.driver_id = $('#driver_id').val(); // envia o filtro do select
      }
    },
    columns: [
      { data: 'placeholder',      name: 'placeholder', orderable: false, searchable: false },
      { data: 'id',               name: 'adjustments.id' },
      { data: 'name',             name: 'adjustments.name' },
      { data: 'type',             name: 'type' },                 // filtrado via filterColumn
      { data: 'amount',           name: 'adjustments.amount' },
      { data: 'percent',          name: 'adjustments.percent' },
      { data: 'start_date',       name: 'adjustments.start_date' },
      { data: 'end_date',         name: 'adjustments.end_date' },
      { data: 'drivers',          name: 'drivers' },              // filtrado via whereHas
      { data: 'company_name',     name: 'company_name' },         // filtrado via whereHas
      { data: 'company_expense',  name: 'company_expense', orderable:false, searchable:false },
      { data: 'car_hire_deduct',  name: 'car_hire_deduct', orderable:false, searchable:false },
      { data: 'actions',          name: 'actions', orderable:false, searchable:false },
    ],
    orderCellsTop: true,
    order: [[1, 'desc']],
    pageLength: 100,
  };

  let table = $('.datatable-Adjustment').DataTable(dtOverrideGlobals);

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
      $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });

  // Pesquisa por coluna (inputs no 2º thead)
  let visibleColumnsIndexes = null;
  $(document).on('input change', '.datatable-Adjustment thead .search', function () {
      const $el   = $(this);
      const strict = $el.attr('strict') || false;
      const raw   = $el.val();
      const value = strict && raw !== '' ? '^' + raw + '$' : raw;

      let index = $el.closest('th').index();
      if (visibleColumnsIndexes !== null) index = visibleColumnsIndexes[index];

      table.column(index).search(value, !!strict).draw();
  });

  table.on('column-visibility.dt', function () {
      visibleColumnsIndexes = [];
      table.columns(':visible').every(function (colIdx) {
          visibleColumnsIndexes.push(colIdx);
      });
  });

  // Recarrega quando muda o select do Driver
  $('#driver_id').on('change', function () {
      table.ajax.reload();
  });
});
</script>
@endsection
