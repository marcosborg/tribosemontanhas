@extends('layouts.admin')
@section('content')
<div class="content">
    @can('car_hire_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.car-hires.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.carHire.title_singular') }}
                </a>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                    {{ trans('global.app_csvImport') }}
                </button>
                @include('csvImport.modal', ['model' => 'CarHire', 'route' => 'admin.car-hires.parseCsvImport'])
            </div>
        </div>
    @endcan

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.carHire.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-CarHire" style="width:100%">
                        <thead>
                            <tr>
                                <th width="10"></th>
                                <th>{{ trans('cruds.carHire.fields.id') }}</th>
                                <th>{{ trans('cruds.carHire.fields.name') }}</th>
                                <th>{{ trans('cruds.carHire.fields.amount') }}</th>
                                <th>{{ trans('cruds.carHire.fields.start_date') }}</th>
                                <th>{{ trans('cruds.carHire.fields.end_date') }}</th>
                                <th>{{ trans('cruds.carHire.fields.driver') }}</th>
                                <th>&nbsp;</th>
                            </tr>
                            {{-- Filtros por coluna --}}
                            <tr>
                                <th></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
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

  @can('car_hire_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.car-hires.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      const ids = $.map(dt.rows({ selected: true }).data(), function (entry) {
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
    aaSorting: [],
    ajax: "{{ route('admin.car-hires.index') }}",
    columns: [
      { data: 'placeholder',  name: 'placeholder', orderable:false, searchable:false },
      { data: 'id',           name: 'car_hires.id' },
      { data: 'name',         name: 'car_hires.name' },
      { data: 'amount',       name: 'car_hires.amount' },
      { data: 'start_date',   name: 'car_hires.start_date' },
      { data: 'end_date',     name: 'car_hires.end_date' },
      { data: 'driver_name',  name: 'driver_name' }, // filtrado via whereHas
      { data: 'actions',      name: 'actions', orderable:false, searchable:false }
    ],
    orderCellsTop: true,
    order: [[1, 'desc']],
    pageLength: 100,
  };

  const table = $('.datatable-CarHire').DataTable(dtOverrideGlobals);

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
      $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });

  // Pesquisa por coluna (2ª linha do thead)
  let visibleColumnsIndexes = null;
  $(document).on('input change', '.datatable-CarHire thead .search', function () {
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
});
</script>
@endsection
