@extends('layouts.admin')

@section('content')
<div class="content">
    @can('car_track_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.car-tracks.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.carTrack.title_singular') }}
                </a>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                    {{ trans('global.app_csvImport') }}
                </button>
                @include('csvImport.modal', ['model' => 'CarTrack', 'route' => 'admin.car-tracks.parseCsvImport'])
            </div>
        </div>
    @endcan

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.carTrack.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    <table id="cartrackTable" class="table table-bordered table-striped table-hover ajaxTable datatable datatable-CarTrack" style="width:100%">
                        <thead>
                            <tr>
                                <th width="10"></th>
                                <th>{{ trans('cruds.carTrack.fields.id') }}</th>
                                <th>{{ trans('cruds.carTrack.fields.tvde_week') }}</th>
                                <th>{{ trans('cruds.carTrack.fields.date') }}</th>
                                <th>{{ trans('cruds.carTrack.fields.license_plate') }}</th>
                                <th>Motorista</th>
                                <th>Existe</th>
                                <th>{{ trans('cruds.carTrack.fields.value') }}</th>
                                <th>&nbsp;</th>
                            </tr>
                            <tr class="filters">
                                <th></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="car_tracks.id"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="tvde_weeks.start_date"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="car_tracks.date"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="car_tracks.license_plate"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="Pesquisar motorista" data-col-name="driver_name"></th>
                                <th></th> {{-- Existe (badge) - não pesquisável --}}
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="car_tracks.value"></th>
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
  $.fn.dataTable.ext.errMode = 'throw';

  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)

  @can('car_track_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.car-tracks.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      var ids = $.map(dt.rows({ selected: true }).data(), function (entry) { return entry.id });
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

  const $table = $('#cartrackTable');

  let table = $table.DataTable({
    buttons: dtButtons,
    processing: true,
    serverSide: true,
    retrieve: true,
    aaSorting: [],
    searchDelay: 250,
    ajax: "{{ route('admin.car-tracks.index') }}",
    columns: [
      { data: 'placeholder',           name: 'placeholder', searchable:false, orderable:false },
      { data: 'id',                     name: 'car_tracks.id' },
      { data: 'tvde_week_start_date',   name: 'tvde_weeks.start_date' },
      { data: 'date',                   name: 'car_tracks.date' },
      { data: 'license_plate',          name: 'car_tracks.license_plate' },
      { data: 'driver_name',            name: 'driver_name', orderable:false },
      { data: 'exist',                  name: 'exist', searchable:false, orderable:false }, // NOVA COLUNA
      { data: 'value',                  name: 'car_tracks.value' },
      { data: 'actions',                name: 'actions', searchable:false, orderable:false }
    ],
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,

    initComplete: function () {
      const api        = this.api();
      const $theadLive = $(api.table().header());
      const $filters   = $('#cartrackTable thead tr.filters');

      if ($filters.length) $filters.appendTo($theadLive);
      $theadLive.find('tr.filters th').off('click.DT');

      $theadLive.off('input.colFilter change.colFilter')
        .on('input.colFilter change.colFilter', 'tr.filters input, tr.filters select', function (e) {
          const colName = $(this).data('col-name');
          const strict  = $(this).attr('strict') || false;
          const rawVal  = this.value;
          const value   = (strict && rawVal) ? '^' + rawVal + '$' : rawVal;

          let colIdx = null;
          if (colName) {
            const colRef = api.column(colName + ':name');
            colIdx = colRef ? colRef.index() : null;
          }

          if (colIdx !== null && colIdx !== undefined) {
            const col = api.column(colIdx);
            if (col.search() !== value) col.search(value, !!strict, false).draw();
          }

          e.stopPropagation();
        });
    }
  });

  table.on('preXhr.dt', function (e, settings, data) {
      try {
        console.log('[preXhr] columns.search:', data.columns.map(c => ({name:c.name, search:c.search.value})));
      } catch (_) {}
  });
  table.on('draw.dt', function () {
      console.log('[draw] linhas na página:', table.rows({page:'current'}).count());
  });

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
      $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });
});
</script>
@endsection
