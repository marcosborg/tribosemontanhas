@extends('layouts.admin')

@section('content')
<div class="content">
    @can('tesla_charging_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.tesla-chargings.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.teslaCharging.title_singular') }}
                </a>
                <select class="form-control select2" id="tesla_import_week_selector" style="width: 260px; display: inline-block; vertical-align: middle;">
                    @foreach($tvdeWeeks as $tvdeWeek)
                        <option value="{{ $tvdeWeek->id }}" {{ (string) old('tvde_week_id', $selectedWeekId) === (string) $tvdeWeek->id ? 'selected' : '' }}>
                            {{ $tvdeWeek->start_date }} a {{ $tvdeWeek->end_date }}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-info" type="button" data-toggle="collapse" data-target="#teslaImportPanel" aria-expanded="false" aria-controls="teslaImportPanel">
                    Importar Tesla Charging
                </button>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                    {{ trans('global.app_csvImport') }}
                </button>
                @include('csvImport.modal', ['model' => 'TeslaCharging', 'route' => 'admin.tesla-chargings.parseCsvImport'])
            </div>
        </div>
    @endcan

    @can('tesla_charging_create')
        <div class="row">
            <div class="col-lg-12">
                <div id="teslaImportPanel" class="panel panel-default collapse {{ session('open_import_panel') === 'tesla' || $errors->has('report_file') || $errors->has('tvde_week_id') ? 'in' : '' }}" style="margin-top: 10px;">
                    <div class="panel-heading">
                        Importar relatório Tesla Charging
                    </div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.tesla-chargings.importReport') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tvde_week_id" id="tesla_import_week_hidden" value="{{ old('tvde_week_id', $selectedWeekId) }}">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group {{ session('open_import_panel') === 'tesla' && $errors->has('report_file') ? 'has-error' : '' }}">
                                        <label class="required" for="tesla_report_file">Ficheiro Tesla Charging</label>
                                        <input class="form-control" type="file" name="report_file" id="tesla_report_file" accept=".csv,.txt" required>
                                        @if(session('open_import_panel') === 'tesla' && $errors->has('report_file'))
                                            <span class="help-block" role="alert">{{ $errors->first('report_file') }}</span>
                                        @endif
                                        <span class="help-block">Semana escolhida no topo. Mapeamento: data=B, VIN=F, valor=W.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group" style="margin-top: 25px;">
                                        <button class="btn btn-primary" type="submit">Processar Tesla Charging</button>
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
                    {{ trans('cruds.teslaCharging.title_singular') }} {{ trans('global.list') }}
                </div>

                <div class="panel-body">
                    <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-TeslaCharging" style="width:100%">
                        <thead>
                            <tr>
                                <th width="10"></th>
                                <th>{{ trans('cruds.teslaCharging.fields.id') }}</th>
                                <th>{{ trans('cruds.teslaCharging.fields.value') }}</th>
                                <th>{{ trans('cruds.teslaCharging.fields.license') }}</th>
                                <th>{{ trans('cruds.teslaCharging.fields.datetime') }}</th>
                                <th>&nbsp;</th>
                            </tr>
                            <tr>
                                <td></td>
                                <td><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
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
  const $teslaWeekSelector = $('#tesla_import_week_selector')
  const $teslaWeekHidden = $('#tesla_import_week_hidden')

  if ($teslaWeekSelector.length) {
    $teslaWeekSelector.on('change', function () {
      if ($teslaWeekHidden.length) {
        $teslaWeekHidden.val(this.value)
      }
    })
  }
  // Botões (mass delete etc.)
  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)

  @can('tesla_charging_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}'
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.tesla-chargings.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      let ids = $.map(dt.rows({ selected: true }).data(), function (entry) {
        return entry.id
      })

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

  // Configuração DataTable
  let dtOverrideGlobals = {
    buttons: dtButtons,
    processing: true,
    serverSide: true,
    retrieve: true,
    aaSorting: [],
    ajax: "{{ route('admin.tesla-chargings.index') }}",
    columns: [
      { data: 'placeholder', name: 'placeholder', searchable: false, orderable: false },
      { data: 'id',        name: 'tesla_chargings.id' },
      { data: 'value',     name: 'tesla_chargings.value' },
      { data: 'license',   name: 'tesla_chargings.license' },
      { data: 'datetime',  name: 'tesla_chargings.datetime' },
      { data: 'actions',   name: 'actions', searchable: false, orderable: false }
    ],
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  }

  let table = $('.datatable-TeslaCharging').DataTable(dtOverrideGlobals)

  // Ajuste em tabs (se existirem)
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
      $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });

  // Pesquisa por coluna (com suporte a column-visibility)
  let visibleColumnsIndexes = null

  // Delegado para apanhar o cabeçalho correto mesmo com FixedHeader/clone
  $(document).on('input', '.datatable-TeslaCharging thead .search', function () {
      let strict = $(this).attr('strict') || false
      let value  = strict && this.value ? "^" + this.value + "$" : this.value

      // usar TH do header correspondente
      let index = $(this).closest('th').index()
      if (visibleColumnsIndexes !== null) {
        index = visibleColumnsIndexes[index]
      }

      table
        .column(index)
        .search(value, strict)
        .draw()
  })

  table.on('column-visibility.dt', function(){
      visibleColumnsIndexes = []
      table.columns(":visible").every(function(colIdx){
          visibleColumnsIndexes.push(colIdx)
      })
  })
})
</script>
@endsection
