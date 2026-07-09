@extends('layouts.admin')

@section('content')
<div class="content">
    @can('car_track_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.car-tracks.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.carTrack.title_singular') }}
                </a>
                <select class="form-control select2" id="car_track_import_week_selector" style="width: 260px; display: inline-block; vertical-align: middle;">
                    @foreach($tvdeWeeks as $tvdeWeek)
                        <option value="{{ $tvdeWeek->id }}" {{ (string) old('tvde_week_id', $selectedWeekId) === (string) $tvdeWeek->id ? 'selected' : '' }}>
                            {{ $tvdeWeek->start_date }} a {{ $tvdeWeek->end_date }}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-info" type="button" data-toggle="collapse" data-target="#viaVerdeImportPanel" aria-expanded="false" aria-controls="viaVerdeImportPanel">
                    Importar Via Verde
                </button>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                    {{ trans('global.app_csvImport') }}
                </button>
                @include('csvImport.modal', ['model' => 'CarTrack', 'route' => 'admin.car-tracks.parseCsvImport'])
            </div>
        </div>
    @endcan

    @can('car_track_create')
        <div class="row">
            <div class="col-lg-12">
                <div id="viaVerdeImportPanel" class="panel panel-default collapse {{ session('open_import_panel') === 'via_verde' || $errors->has('report_file') || $errors->has('tvde_week_id') ? 'in' : '' }}" style="margin-top: 10px;">
                    <div class="panel-heading">
                        Importar relatorio Via Verde
                    </div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.car-tracks.importViaVerde') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tvde_week_id" id="car_track_import_week_hidden" value="{{ old('tvde_week_id', $selectedWeekId) }}">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group {{ session('open_import_panel') === 'via_verde' && $errors->has('report_file') ? 'has-error' : '' }}">
                                        <label class="required" for="via_verde_report_file">Ficheiro Via Verde</label>
                                        <input class="form-control" type="file" name="report_file" id="via_verde_report_file" accept=".csv,.txt,.xlsx" required>
                                        @if(session('open_import_panel') === 'via_verde' && $errors->has('report_file'))
                                            <span class="help-block" role="alert">{{ $errors->first('report_file') }}</span>
                                        @endif
                                        <span class="help-block">Semana escolhida no topo. O import classifica automaticamente motorista, empresa ou validacao manual.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group" style="margin-top: 25px;">
                                        <button class="btn btn-primary" type="submit">Processar Via Verde</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
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
                                <th>Destino</th>
                                <th>Motivo</th>
                                <th>Motorista</th>
                                <th>Empresa</th>
                                <th>{{ trans('cruds.carTrack.fields.value') }}</th>
                                <th>&nbsp;</th>
                            </tr>
                            <tr class="filters">
                                <th></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="car_tracks.id"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="tvde_weeks.start_date"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="car_tracks.date"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="car_tracks.license_plate"></th>
                                <th>
                                    <select class="form-control input-sm" data-col-name="classification_destination">
                                        <option value="">Todos</option>
                                        <option value="Motorista">Motorista</option>
                                        <option value="Empresa">Empresa</option>
                                        <option value="manual">Validacao manual</option>
                                    </select>
                                </th>
                                <th><input class="form-control input-sm" type="text" placeholder="Pesquisar motivo" data-col-name="classification_reason_label"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="Pesquisar motorista" data-col-name="driver_name"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="Pesquisar empresa" data-col-name="company_name"></th>
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

  const $carTrackWeekSelector = $('#car_track_import_week_selector');
  const $carTrackWeekHidden = $('#car_track_import_week_hidden');

  if ($carTrackWeekSelector.length) {
    $carTrackWeekSelector.on('change', function () {
      if ($carTrackWeekHidden.length) {
        $carTrackWeekHidden.val(this.value);
      }
    });
  }

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
      { data: 'placeholder', name: 'placeholder', searchable:false, orderable:false },
      { data: 'id', name: 'car_tracks.id' },
      { data: 'tvde_week_start_date', name: 'tvde_weeks.start_date' },
      { data: 'date', name: 'car_tracks.date' },
      { data: 'license_plate', name: 'car_tracks.license_plate' },
      { data: 'classification_destination', name: 'classification_destination', orderable:false },
      { data: 'classification_reason_label', name: 'classification_reason_label', orderable:false },
      { data: 'driver_name', name: 'driver_name', orderable:false },
      { data: 'company_name', name: 'company_name', orderable:false },
      { data: 'value', name: 'car_tracks.value' },
      { data: 'actions', name: 'actions', searchable:false, orderable:false }
    ],
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,

    initComplete: function () {
      const api = this.api();
      const $theadLive = $(api.table().header());
      const $filters = $('#cartrackTable thead tr.filters');

      if ($filters.length) $filters.appendTo($theadLive);
      $theadLive.find('tr.filters th').off('click.DT');

      $theadLive.off('input.colFilter change.colFilter')
        .on('input.colFilter change.colFilter', 'tr.filters input, tr.filters select', function (e) {
          const colName = $(this).data('col-name');
          const strict = $(this).attr('strict') || false;
          const rawVal = this.value;
          const value = (strict && rawVal) ? '^' + rawVal + '$' : rawVal;

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

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
      $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });
});
</script>
@endsection
