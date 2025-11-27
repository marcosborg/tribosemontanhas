@extends('layouts.admin')

@section('content')
<div class="content">
    @can('driver_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.drivers.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.driver.title_singular') }}
                </a>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">
                    {{ trans('global.app_csvImport') }}
                </button>
                @include('csvImport.modal', ['model' => 'Driver', 'route' => 'admin.drivers.parseCsvImport'])
            </div>
        </div>
    @endcan

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.driver.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-Driver" style="width:100%">
                        <thead>
                            <tr>
                              <th width="10"></th>
                              <th>{{ trans('cruds.driver.fields.id') }}</th>
                              <th>{{ trans('cruds.driver.fields.user') }}</th>
                              <th>{{ trans('cruds.user.fields.email') }}</th>
                              <th>{{ trans('cruds.driver.fields.code') }}</th>
                              <th>{{ trans('cruds.driver.fields.contract_vat') }}</th>
                              <th>{{ trans('cruds.driver.fields.state') }}</th>
                              <th>{{ trans('cruds.driver.fields.payment_vat') }}</th>
                              <th>{{ trans('cruds.driver.fields.driver_vat') }}</th>
                              <th>{{ trans('cruds.driver.fields.uber_uuid') }}</th>
                              <th>{{ trans('cruds.driver.fields.bolt_name') }}</th>
                              <th>{{ trans('cruds.driver.fields.company') }}</th>
                              <th>&nbsp;</th>
                          </tr>
                            {{-- Filtros por coluna --}}
                            <tr>
                              <th></th>
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- id --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- user --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- email --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- code --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- contract_vat --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- state --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- payment_vat --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- driver_vat --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- uber_uuid --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- bolt_name --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- company --}}
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

@section('styles')
<style>
    .datatable-Driver tbody tr { cursor: pointer; }
</style>
@endsection

@section('scripts')
@parent
<script>
$(function () {
  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)

  @can('driver_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.drivers.massDestroy') }}",
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

  let dtOverrideGlobals = {
    buttons: dtButtons,
    processing: true,
    serverSide: true,
    retrieve: true,
    aaSorting: [],
    ajax: "{{ route('admin.drivers.index') }}",
    columns: [
      { data: 'placeholder',   name: 'placeholder', orderable: false, searchable: false },
      { data: 'id',            name: 'drivers.id' },

      { data: 'user_name',     name: 'user_name' },
      { data: 'user.email',    name: 'user.email' },

      { data: 'code',               name: 'drivers.code' },
      { data: 'contract_vat_name',  name: 'contract_vat_name' },
      { data: 'state_name',         name: 'state_name' },

      { data: 'payment_vat',        name: 'drivers.payment_vat' },
      { data: 'driver_vat',         name: 'drivers.driver_vat' },

      { data: 'uber_uuid',          name: 'drivers.uber_uuid' },
      { data: 'bolt_name',          name: 'drivers.bolt_name' },
      { data: 'company_name',       name: 'company_name' },

      { data: 'actions',       name: 'actions', orderable: false, searchable: false },
    ],
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  };

  let table = $('.datatable-Driver').DataTable(dtOverrideGlobals);

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });

  // Pesquisa por coluna (inputs no segundo thead)
  let visibleColumnsIndexes = null;
  $(document).on('input change', '.datatable-Driver thead .search', function () {
      const $el = $(this);
      const strict = $el.attr('strict') || false; // se quiseres selects com strict, podes usar
      const rawVal = $el.val();
      const value  = strict && rawVal !== '' ? '^' + rawVal + '$' : rawVal;

      let index = $el.closest('th').index();
      if (visibleColumnsIndexes !== null) {
        index = visibleColumnsIndexes[index];
      }

      table
        .column(index)
        .search(value, !!strict) // usa regex quando strict = true
        .draw();
  });

  table.on('column-visibility.dt', function(){
      visibleColumnsIndexes = [];
      table.columns(':visible').every(function(colIdx) {
          visibleColumnsIndexes.push(colIdx);
      });
  });

  // Linhas clicáveis (leva para edit), mas ignorando cliques em botões/links/inputs
  $('.datatable-Driver tbody').on('click', 'tr', function (e) {
      const isInteractive = $(e.target).closest('a, button, input, label, .dropdown, .btn').length > 0;
      if (isInteractive) return;

      const rowData = table.row(this).data();
      if (rowData && rowData.id) {
          window.location = '/admin/drivers/' + rowData.id + '/edit';
      }
  });
});
</script>
@endsection
