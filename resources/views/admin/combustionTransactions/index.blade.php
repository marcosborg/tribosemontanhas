@extends('layouts.admin')
@section('content')
<div class="content">
    @can('combustion_transaction_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.combustion-transactions.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.combustionTransaction.title_singular') }}
                </a>
                <select class="form-control select2" id="prio_import_week_selector" style="width: 260px; display: inline-block; vertical-align: middle;">
                    @foreach($tvdeWeeks as $tvdeWeek)
                        <option value="{{ $tvdeWeek->id }}" {{ (string) old('tvde_week_id', $selectedWeekId) === (string) $tvdeWeek->id ? 'selected' : '' }}>
                            {{ $tvdeWeek->start_date }} a {{ $tvdeWeek->end_date }}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-info" type="button" data-toggle="collapse" data-target="#prioElectricImportPanel" aria-expanded="false" aria-controls="prioElectricImportPanel">
                    Importar Prio Electric
                </button>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                    {{ trans('global.app_csvImport') }}
                </button>
                @include('csvImport.modal', ['model' => 'CombustionTransaction', 'route' => 'admin.combustion-transactions.parseCsvImport'])
            </div>
        </div>
    @endcan

    @can('combustion_transaction_create')
        <div class="row">
            <div class="col-lg-12">
                <div id="prioElectricImportPanel" class="panel panel-default collapse {{ session('open_import_panel') === 'prio-electric' ? 'in' : '' }}" style="margin-top: 10px;">
                    <div class="panel-heading">
                        Importar Prio Electric
                    </div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.combustion-transactions.importPrioElectric') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tvde_week_id" id="prio_import_week_hidden" value="{{ old('tvde_week_id', $selectedWeekId) }}">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group {{ session('open_import_panel') === 'prio-electric' && $errors->has('report_file') ? 'has-error' : '' }}">
                                        <label class="required" for="prio_report_file">Ficheiro Prio Electric</label>
                                        <input class="form-control" type="file" name="report_file" id="prio_report_file" accept=".csv,.txt,.xlsx,.xls" required>
                                        @if(session('open_import_panel') === 'prio-electric' && $errors->has('report_file'))
                                            <span class="help-block" role="alert">{{ $errors->first('report_file') }}</span>
                                        @endif
                                        <span class="help-block">Remove imagens e as 3 primeiras linhas, usa A=data, B=cartão, M=total com IVA, `amount=0` e a semana escolhida no topo.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group" style="margin-top: 25px;">
                                        <button class="btn btn-primary" type="submit">Processar Prio Electric</button>
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
                    {{ trans('cruds.combustionTransaction.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-CombustionTransaction" style="width:100%">
                        <thead>
                            <tr>
                                <th width="10"></th>
                                <th>{{ trans('cruds.combustionTransaction.fields.id') }}</th>
                                <th>{{ trans('cruds.combustionTransaction.fields.tvde_week') }}</th>
                                <th>{{ trans('cruds.combustionTransaction.fields.card') }}</th>
                                <th>Existe</th>
                                <th>{{ trans('cruds.combustionTransaction.fields.amount') }}</th>
                                <th>{{ trans('cruds.combustionTransaction.fields.total') }}</th>
                                <th>{{ trans('cruds.combustionTransaction.fields.transaction_date') }}</th>
                                <th>&nbsp;</th>
                            </tr>
                            {{-- Filtros por coluna --}}
                            <tr>
                                <th></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                {{-- Filtro Sim/Não para "Existe" --}}
                                <th>
                                    <select class="search form-control input-sm">
                                        <option value="">{{ __('Todos') }}</option>
                                        <option value="Sim">Sim</option>
                                        <option value="Não">Não</option>
                                    </select>
                                </th>
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
  const $prioWeekSelector = $('#prio_import_week_selector');
  const $prioWeekHidden = $('#prio_import_week_hidden');

  if ($prioWeekSelector.length && $prioWeekHidden.length) {
    $prioWeekSelector.on('change', function () {
      $prioWeekHidden.val(this.value);
    });
  }

  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)

  @can('combustion_transaction_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.combustion-transactions.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      const ids = $.map(dt.rows({ selected: true }).data(), function (entry) {
        return entry.id
      });
      if (ids.length === 0) {
        alert('{{ trans('global.datatables.zero_selected') }}'); return;
      }
      if (confirm('{{ trans('global.areYouSure') }}')) {
        $.ajax({
          headers: { 'x-csrf-token': _token },
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
    ajax: "{{ route('admin.combustion-transactions.index') }}",
    columns: [
      { data: 'placeholder',           name: 'placeholder', orderable: false, searchable: false },
      { data: 'id',                     name: 'combustion_transactions.id' },
      { data: 'tvde_week_start_date',   name: 'tvde_week_start_date' },        // filtrado via filterColumn
      { data: 'card',                   name: 'combustion_transactions.card' },
      { data: 'exist',                  name: 'exist', orderable: false, searchable: true },
      { data: 'amount',                 name: 'combustion_transactions.amount' },
      { data: 'total',                  name: 'combustion_transactions.total' },
      { data: 'transaction_date',       name: 'combustion_transactions.transaction_date' },
      { data: 'actions',                name: 'actions', orderable: false, searchable: false },
    ],
    orderCellsTop: true,
    order: [[1, 'desc']],
    pageLength: 100,
  };

  const table = $('.datatable-CombustionTransaction').DataTable(dtOverrideGlobals);

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function () {
    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });

  // Pesquisa por coluna (inputs na 2ª linha do thead)
  let visibleColumnsIndexes = null;
  $(document).on('input change', '.datatable-CombustionTransaction thead .search', function () {
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
