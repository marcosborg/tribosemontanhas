@extends('layouts.admin')
@section('content')
<div class="content">
    @can('card_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.cards.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.card.title_singular') }}
                </a>
            </div>
        </div>
    @endcan
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.card.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-Card" style="width:100%">
                            <thead>
                                <tr>
                                    <th width="10">

                                    </th>
                                    <th>
                                        {{ trans('cruds.card.fields.id') }}
                                    </th>
                                    <th>
                                        {{ trans('cruds.card.fields.type') }}
                                    </th>
                                    <th>
                                        {{ trans('cruds.card.fields.code') }}
                                    </th>
                                    <th>
                                        {{ trans('cruds.card.fields.company') }}
                                    </th>
                                    <th>
                                        Motorista atribuído
                                    </th>
                                    <th>
                                        &nbsp;
                                    </th>
                                </tr>
                                <tr>
                                    <th></th>
                                    <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
                                    <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>
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
</div>
@endsection
@section('scripts')
@parent
<script>
    $(function () {
      let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)

@can('card_delete')
      let deleteButtonTrans = '{{ trans('global.datatables.delete') }}'
      let deleteButton = {
        text: deleteButtonTrans,
        url: "{{ route('admin.cards.massDestroy') }}",
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
              data: { ids: ids, _method: 'DELETE' }})
              .done(function () { location.reload() })
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
        ajax: "{{ route('admin.cards.index') }}",
        columns: [
          { data: 'placeholder',           name: 'placeholder', orderable: false, searchable: false },
          { data: 'id',                    name: 'id' },
          { data: 'type',                  name: 'type' },
          { data: 'code',                  name: 'code' },
          { data: 'company_name',          name: 'company_name' },
          { data: 'assigned_driver_names', name: 'assigned_driver_names' },
          { data: 'actions',               name: 'actions', orderable: false, searchable: false }
        ],
        orderCellsTop: true,
        order: [[ 1, 'desc' ]],
        pageLength: 100,
        initComplete: function () {
          let api = this.api();

          api.columns().every(function (colIdx) {
            let cell = $('.datatable-Card thead tr:eq(1) th').eq(colIdx);
            let input = cell.find('input.search, select.search');

            if (!input.length) {
              return;
            }

            $(input).off('.dtcolsearch');
            $(input).on('keyup.dtcolsearch change.dtcolsearch input.dtcolsearch', function () {
              api.column(colIdx).search(this.value).draw();
            });
          });
        }
      };

      let table = $('.datatable-Card').DataTable(dtOverrideGlobals)
      $('a[data-toggle="tab"]').on('shown.bs.tab click', function(e){
          $($.fn.dataTable.tables(true)).DataTable()
              .columns.adjust();
      });
    })

</script>
@endsection
