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
                    <div class="table-responsive">
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
                              <th>{{ trans('cruds.driver.fields.driver_vat') }}</th>
                              <th>{{ trans('cruds.driver.fields.uber_uuid') }}</th>
                              <th>{{ trans('cruds.driver.fields.bolt_name') }}</th>
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
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- driver_vat --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- uber_uuid --}}
                              <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th> {{-- bolt_name --}}
                              <th></th>
                          </tr>
                        </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    .datatable-Driver tbody tr { cursor: default; }
    .table-responsive { overflow-x: auto; }
    .datatable-scroll-top { overflow-x: scroll; overflow-y: hidden; height: 14px; }
    .datatable-scroll-top-inner { height: 1px; }
</style>
@endsection

@section('scripts')
@parent
<script>
$(function () {
  const $tableEl = $('.datatable-Driver');
  const $panelBody = $tableEl.closest('.panel-body');

  function getScrollY() {
    const top = $panelBody.offset() ? $panelBody.offset().top : 0;
    const $wrapper = $panelBody.find('.dataTables_wrapper');
    const $buttons = $wrapper.find('.dt-buttons:visible').first();
    const $filter = $wrapper.find('.dataTables_filter:visible').first();
    const headerExtra =
      ($buttons.outerHeight(true) || 0) +
      ($filter.outerHeight(true) || 0);
    const available = $(window).height() - top - headerExtra - 240;
    return Math.max(200, available) + 'px';
  }

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
    scrollX: true,
    scrollY: getScrollY(),
    scrollCollapse: true,
    ajax: "{{ route('admin.drivers.index') }}",
    columns: [
      { data: 'placeholder',   name: 'placeholder', orderable: false, searchable: false },
      { data: 'id',            name: 'drivers.id' },

      { data: 'user_name',     name: 'user_name' },
      { data: 'user.email',    name: 'user.email' },

      { data: 'code',               name: 'drivers.code' },
      { data: 'contract_vat_name',  name: 'contract_vat_name' },
      { data: 'state_name',         name: 'state_name' },

      { data: 'driver_vat',         name: 'drivers.driver_vat' },

      { data: 'uber_uuid',          name: 'drivers.uber_uuid' },
      { data: 'bolt_name',          name: 'drivers.bolt_name' },

      { data: 'actions',       name: 'actions', orderable: false, searchable: false },
    ],
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  };

  let table = $tableEl.DataTable(dtOverrideGlobals);

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });

  const $scrollBody = $('.dataTables_scrollBody');
  const $scrollHead = $('.dataTables_scrollHead');
  const $scrollTop = $('<div class="datatable-scroll-top"><div class="datatable-scroll-top-inner"></div></div>');
  const $scrollTopInner = $scrollTop.find('.datatable-scroll-top-inner');

  if ($scrollHead.length) {
      $scrollTop.insertBefore($scrollHead);
  }

  function syncScrollWidth() {
      const tableWidth = $scrollBody.find('table').outerWidth() || 0;
      $scrollTopInner.width(tableWidth);
  }

  $scrollTop.on('scroll', function () {
      $scrollBody.scrollLeft($scrollTop.scrollLeft());
  });

  $scrollBody.on('scroll', function () {
      $scrollTop.scrollLeft($scrollBody.scrollLeft());
  });

  syncScrollWidth();
  table.on('draw', syncScrollWidth);

  function resizeScrollBody() {
      const newY = getScrollY();
      const settings = table.settings()[0];
      settings.oScroll.sY = newY;
      $scrollBody.css('height', newY).css('max-height', newY);
      table.columns.adjust();
      syncScrollWidth();
  }
  $(window).on('resize', resizeScrollBody);
  resizeScrollBody();
  $(window).on('resize', syncScrollWidth);

  // Pesquisa por coluna (inputs no segundo thead)
  let visibleColumnsIndexes = null;
  // Com scrollX/scrollY o DataTables clona o <thead> para a zona fixa do header.
  // Por isso, escutamos tanto no <thead> original como no <thead> clonado.
  const searchSelector = '.datatable-Driver thead .search, .dataTables_scrollHead thead .search';
  $(document).on('input change', searchSelector, function () {
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
});
</script>
@endsection
