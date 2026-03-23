@extends('layouts.admin')

@section('content')
<div class="content">
    @can('tvde_activity_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.tvde-activities.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.tvdeActivity.title_singular') }}
                </a>
                <select class="form-control select2" id="uber_import_week_selector" style="width: 260px; display: inline-block; vertical-align: middle;">
                    @foreach($tvdeWeeks as $tvdeWeek)
                        <option value="{{ $tvdeWeek->id }}" {{ (string) old('tvde_week_id', $selectedWeekId) === (string) $tvdeWeek->id ? 'selected' : '' }}>
                            {{ $tvdeWeek->start_date }} a {{ $tvdeWeek->end_date }}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-info" type="button" data-toggle="collapse" data-target="#uberImportPanel" aria-expanded="false" aria-controls="uberImportPanel">
                    Importar Uber semanal
                </button>
                <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#boltImportPanel" aria-expanded="false" aria-controls="boltImportPanel">
                    Importar Bolt semanal
                </button>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                    {{ trans('global.app_csvImport') }}
                </button>
                @include('csvImport.modal', ['model' => 'TvdeActivity', 'route' => 'admin.tvde-activities.parseCsvImport'])
            </div>
        </div>
    @endcan

    @can('tvde_activity_create')
        <div class="row">
            <div class="col-lg-12">
                <div id="uberImportPanel" class="panel panel-default collapse {{ $errors->has('report_file') || $errors->has('tvde_week_id') ? 'in' : '' }}" style="margin-top: 10px;">
                    <div class="panel-heading">
                        Importar relatório semanal Uber
                    </div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.tvde-activities.importUber') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tvde_week_id" id="uber_import_week_hidden" value="{{ old('tvde_week_id', $selectedWeekId) }}">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group {{ $errors->has('report_file') ? 'has-error' : '' }}">
                                        <label class="required" for="report_file">Ficheiro Uber</label>
                                        <input class="form-control" type="file" name="report_file" id="report_file" accept=".csv,.txt,.xlsx,.xls" required>
                                        @if($errors->has('report_file'))
                                            <span class="help-block" role="alert">{{ $errors->first('report_file') }}</span>
                                        @endif
                                        <span class="help-block">Semana escolhida no topo. Mapeamento: identificador=A, brutos=G, líquidos=D.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group" style="margin-top: 25px;">
                                        <button class="btn btn-primary" type="submit">Processar Uber</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan

    @can('tvde_activity_create')
        <div class="row">
            <div class="col-lg-12">
                <div id="boltImportPanel" class="panel panel-default collapse {{ session('open_import_panel') === 'bolt' ? 'in' : '' }}" style="margin-top: 10px;">
                    <div class="panel-heading">
                        Importar relatório semanal Bolt
                    </div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.tvde-activities.importBolt') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tvde_week_id" id="bolt_import_week_hidden" value="{{ old('tvde_week_id', $selectedWeekId) }}">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group {{ session('open_import_panel') === 'bolt' && $errors->has('report_file') ? 'has-error' : '' }}">
                                        <label class="required" for="bolt_report_file">Ficheiro Bolt</label>
                                        <input class="form-control" type="file" name="report_file" id="bolt_report_file" accept=".csv,.txt,.xlsx,.xls" required>
                                        @if(session('open_import_panel') === 'bolt' && $errors->has('report_file'))
                                            <span class="help-block" role="alert">{{ $errors->first('report_file') }}</span>
                                        @endif
                                        <span class="help-block">Semana escolhida no topo. Mapeamento: identificador=AB, brutos=E, líquidos=V.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group" style="margin-top: 25px;">
                                        <button class="btn btn-primary" type="submit">Processar Bolt</button>
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
                    {{ trans('cruds.tvdeActivity.title_singular') }} {{ trans('global.list') }}
                </div>

                <div class="panel-body">
                    <table id="tvdeActivitiesTable" class="table table-bordered table-striped table-hover ajaxTable datatable datatable-TvdeActivity" style="width:100%">
                        <thead>
                            <tr>
                                <th width="10"></th>
                                <th>{{ trans('cruds.tvdeActivity.fields.id') }}</th>
                                <th>{{ trans('cruds.tvdeActivity.fields.tvde_week') }}</th>
                                <th>{{ trans('cruds.tvdeActivity.fields.tvde_operator') }}</th>
                                <th>{{ trans('cruds.tvdeActivity.fields.company') }}</th>
                                <th>{{ trans('cruds.tvdeActivity.fields.driver_code') }}</th>
                                <th>Existe</th>
                                <th>{{ trans('cruds.tvdeActivity.fields.gross') }}</th>
                                <th>{{ trans('cruds.tvdeActivity.fields.net') }}</th>
                                <th>&nbsp;</th>
                            </tr>
                            {{-- Linha de filtros por coluna (igual aos Drivers) --}}
                            <tr class="filters">
                                <th></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="tvde_activities.id"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="tvde_weeks.start_date"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="tvde_operators.name"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="companies.name"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="tvde_activities.driver_code"></th>
                                <th>
                                    <select class="form-control input-sm" data-col-name="exists_text">
                                        <option value="">Todos</option>
                                        <option value="Existe">Existe</option>
                                        <option value="Nao existe">Nao existe</option>
                                    </select>
                                </th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="tvde_activities.gross"></th>
                                <th><input class="form-control input-sm" type="text" placeholder="{{ trans('global.search') }}" data-col-name="tvde_activities.net"></th>
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
$.fn.dataTable.ext.errMode = 'throw';

$(function () {
  const $uberWeekSelector = $('#uber_import_week_selector');
  const $uberWeekHidden = $('#uber_import_week_hidden');
  const $boltWeekHidden = $('#bolt_import_week_hidden');

  if ($uberWeekSelector.length) {
    $uberWeekSelector.on('change', function () {
      if ($uberWeekHidden.length) {
        $uberWeekHidden.val(this.value);
      }
      if ($boltWeekHidden.length) {
        $boltWeekHidden.val(this.value);
      }
    });
  }

  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)

  @can('tvde_activity_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.tvde-activities.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      const ids = $.map(dt.rows({ selected: true }).data(), entry => entry.id);
      if (!ids.length) { alert('{{ trans('global.datatables.zero_selected') }}'); return; }
      if (confirm('{{ trans('global.areYouSure') }}')) {
        $.ajax({
          headers: {'x-csrf-token': _token},
          method: 'POST',
          url: config.url,
          data: { ids: ids, _method: 'DELETE' }
        }).done(() => location.reload());
      }
    }
  }
  dtButtons.push(deleteButton)
  @endcan

  const $table = $('#tvdeActivitiesTable');

  let table = $table.DataTable({
    buttons: dtButtons,
    processing: true,
    serverSide: true,
    retrieve: true,
    aaSorting: [],
    orderCellsTop: true,
    searchDelay: 250,
    ajax: "{{ route('admin.tvde-activities.index') }}",
    columns: [
      { data: 'placeholder',           name: 'placeholder', orderable:false, searchable:false },
      { data: 'id',                     name: 'tvde_activities.id' },
      { data: 'tvde_week_start_date',   name: 'tvde_weeks.start_date' },
      { data: 'tvde_operator_name',     name: 'tvde_operators.name' },
      { data: 'company_name',           name: 'companies.name' },
      { data: 'driver_code',            name: 'tvde_activities.driver_code' },
      { data: 'exists_text',            name: 'exists_text' },
      { data: 'gross',                  name: 'tvde_activities.gross' },
      { data: 'net',                    name: 'tvde_activities.net' },
      { data: 'actions',                name: 'actions', orderable:false, searchable:false }
    ],
    order: [[ 1, 'desc' ]],
    pageLength: 100,

    initComplete: function () {
      const api        = this.api();
      const $theadLive = $(api.table().header());
      const $filters   = $('#tvdeActivitiesTable thead tr.filters');

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

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });
});
</script>
@endsection
