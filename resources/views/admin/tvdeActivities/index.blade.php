@extends('layouts.admin')
@section('content')
<div class="content">
    @can('tvde_activity_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.tvde-activities.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.tvdeActivity.title_singular') }}
                </a>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                    {{ trans('global.app_csvImport') }}
                </button>
                @include('csvImport.modal', ['model' => 'TvdeActivity', 'route' => 'admin.tvde-activities.parseCsvImport'])
                <form action="/admin/tvde-activities/delete-filter" method="post" style="margin-top: 10px;">
                @csrf
                <select name="week_filter" class="select2" style="max-width: 200px;">
                    <option selected disabled>Semana</option>
                    @foreach ($tvde_weeks as $tvde_week)
                    <option value="{{ $tvde_week->id }}">{{ $tvde_week->start_date }}</option>
                    @endforeach
                </select>
                <select name="company_filter" class="select2" style="max-width: 200px;">
                    <option selected disabled>Empresa</option>
                    @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
                <button onclick="return confirm('Tem certeza que deseja eliminar os dados do filtro?')" class="btn btn-danger" data-toggle="modal" type="submit">
                    Eliminar seleção de filtro
                </button>
                </form>
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
                    <table class=" table table-bordered table-striped table-hover ajaxTable datatable datatable-TvdeActivity" style="width:100%">
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
                            <tr>
                                <td></td>
                                <td><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>
                                <td><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></td>

                                {{-- Select para Existe/Não existe (usa 1/0) --}}
                                <td>
                                    <select class="search form-control input-sm" strict="true">
                                        <option value="">{{ trans('global.all') }}</option>
                                        <option value="1">Existe</option>
                                        <option value="0">Não existe</option>
                                    </select>
                                </td>

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
  // === Botões (mass delete etc.) ===
  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)

  @can('tvde_activity_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.tvde-activities.massDestroy') }}",
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

  // === DataTable ===
  let dtOverrideGlobals = {
    buttons: dtButtons,
    processing: true,
    serverSide: true,
    retrieve: true,
    aaSorting: [],
    ajax: "{{ route('admin.tvde-activities.index') }}",
    columns: [
      { data: 'placeholder',           name: 'placeholder', orderable: false, searchable: false },
      { data: 'id',                     name: 'tvde_activities.id' },
      { data: 'tvde_week_start_date',   name: 'tvde_weeks.start_date' },   // se não fizeres JOIN, podes deixar sem name qualificado
      { data: 'tvde_operator_name',     name: 'tvde_operators.name' },     // idem
      { data: 'company_name',           name: 'companies.name' },          // idem
      { data: 'driver_code',            name: 'tvde_activities.driver_code' },

      // Coluna visual baseada no exists_flag do servidor.
      // O filtro é tratado por filterColumn('exists', ...) no controller.
      { data: 'exists',                 name: 'exists' },

      { data: 'gross',                  name: 'tvde_activities.gross' },
      { data: 'net',                    name: 'tvde_activities.net' },
      { data: 'actions',                name: 'actions', orderable: false, searchable: false }
    ],
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  };

  let table = $('.datatable-TvdeActivity').DataTable(dtOverrideGlobals);

  // Ajuste quando usas tabs (se existirem)
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });

  // === Pesquisa por coluna (inputs e selects) ===
  let visibleColumnsIndexes = null;
  const $thead = $('.datatable-TvdeActivity thead');

  // Delegado para apanhar inputs/selects incluindo o cabeçalho clonado (FixedHeader)
  $thead.on('input change', '.search', function () {
    const $el = $(this);
    const strict = $el.attr('strict') || false;
    const rawVal = $el.val();
    // Quando strict="true" (ex.: select Existe/Não existe), usamos regex ^valor$
    const value = strict && rawVal !== '' ? '^' + rawVal + '$' : rawVal;

    let index = $el.closest('th').index();
    if (visibleColumnsIndexes !== null) {
      index = visibleColumnsIndexes[index];
    }

    table
      .column(index)
      .search(value, !!strict) // segundo argumento = regex
      .draw();
  });

  table.on('column-visibility.dt', function(){
    visibleColumnsIndexes = [];
    table.columns(':visible').every(function(colIdx){
      visibleColumnsIndexes.push(colIdx);
    });
  });
});
</script>
@endsection
