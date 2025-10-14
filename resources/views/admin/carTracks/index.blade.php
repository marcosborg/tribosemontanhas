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
                                <th>{{ trans('cruds.carTrack.fields.value') }}</th>
                                <th>&nbsp;</th>
                            </tr>
                            <tr class="filters">
                                <td></td>
                                <td><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="form-control input-sm" type="text" placeholder="Pesquisar motorista"></td>
                                <td><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
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
<script>
$(function () {
  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)

  @can('car_track_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.car-tracks.massDestroy') }}",
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

  const $table    = $('#cartrackTable');
  const $thead2   = $table.find('thead tr.filters');

  let table = $table.DataTable({
    buttons: dtButtons,
    processing: true,
    serverSide: true,
    retrieve: true,
    aaSorting: [],
    searchDelay: 250,
    ajax: "{{ route('admin.car-tracks.index') }}",
    columns: [
      { data: 'placeholder',           name: 'placeholder', searchable: false, orderable: false },
      { data: 'id',                     name: 'car_tracks.id' },
      { data: 'tvde_week_start_date',   name: 'tvde_weeks.start_date' },
      { data: 'date',                   name: 'car_tracks.date' },
      { data: 'license_plate',          name: 'car_tracks.license_plate' },
      { data: 'driver_name',            name: 'driver_name', orderable: false }, // searchable por default
      { data: 'value',                  name: 'car_tracks.value' },
      { data: 'actions',                name: 'actions', searchable: false, orderable: false }
    ],
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,

    initComplete: function () {
        const api = this.api();

        // impedir click de ordenar na linha de filtros
        $thead2.find('th').off('click.DT');

        // ligar input/select coluna-a-coluna (usando container do DT, não a tabela original)
        const $container = $(api.table().container());
        const $filterRow = $container.find('thead tr.filters');

        api.columns().every(function (colIdx) {
            const $cell  = $filterRow.find('th').eq(colIdx);
            const $input = $cell.find('input, select');

            if (!$input.length) return;

            console.log('bind: col', colIdx);

            $input.on('keyup change clear', function () {
                const val = this.value;
                console.log('filter: col', colIdx, '->', val);
                if (api.column(colIdx).search() !== val) {
                    api.column(colIdx).search(val).draw();
                }
            });
        });
    }
  });

  // fallback delegado (se por algum motivo o thead for recriado)
  $(document).on('keyup change', '#cartrackTable thead tr.filters input, #cartrackTable thead tr.filters select', function(){
      const api   = $('#cartrackTable').DataTable();
      const $th   = $(this).closest('th');
      const colIx = $th.index();
      const val   = this.value;
      console.log('[fallback] filter: col', colIx, '->', val);
      api.column(colIx).search(val).draw();
  });

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
      $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });
});
</script>
@endsection
