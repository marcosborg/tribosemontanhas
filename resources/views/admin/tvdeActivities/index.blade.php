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
                    <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-TvdeActivity" style="width:100%">
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
                            <tr>
                                <th></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                <th><input class="search form-control input-sm" type="text" placeholder="Existe / Não existe / 1 / 0"></th>
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
$.fn.dataTable.ext.errMode = 'throw'; // mostra erros do DataTables na consola

$(function () {
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

  const table = $('.datatable-TvdeActivity').DataTable({
    buttons: dtButtons,
    processing: true,
    serverSide: true,
    retrieve: true,
    aaSorting: [],
    orderCellsTop: true,
    ajax: "{{ route('admin.tvde-activities.index') }}",
    columns: [
      { data: 'placeholder',           name: 'placeholder', orderable:false, searchable:false },
      { data: 'id',                     name: 'tvde_activities.id' },
      { data: 'tvde_week_start_date',   name: 'tvde_weeks.start_date' },
      { data: 'tvde_operator_name',     name: 'tvde_operators.name' },
      { data: 'company_name',           name: 'companies.name' },
      { data: 'driver_code',            name: 'tvde_activities.driver_code' },

      // Coluna de texto "Existe" calculada no servidor (exists_text) — pesquisável e ordenável
      { data: 'exists_text',            name: 'exists_text' },

      { data: 'gross',                  name: 'tvde_activities.gross' },
      { data: 'net',                    name: 'tvde_activities.net' },
      { data: 'actions',                name: 'actions', orderable:false, searchable:false }
    ],
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  });

  // Pesquisa por coluna (delegado ao THEAD — igual aos Drivers)
  let visibleColumnsIndexes = null;
  $(document).on('input change', '.datatable-TvdeActivity thead .search', function () {
      const $el = $(this);
      const strict = $el.attr('strict') || false;
      const rawVal = $el.val();
      const value  = strict && rawVal !== '' ? '^' + rawVal + '$' : rawVal;

      let index = $el.closest('th').index();
      if (visibleColumnsIndexes !== null) {
        index = visibleColumnsIndexes[index];
      }

      table
        .column(index)
        .search(value, !!strict)
        .draw();
  });

  table.on('column-visibility.dt', function(){
      visibleColumnsIndexes = [];
      table.columns(':visible').every(function(colIdx) {
          visibleColumnsIndexes.push(colIdx);
      });
  });

  // Ajuste quando usas tabs (se existirem)
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });
});
</script>
@endsection
