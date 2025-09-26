@extends('layouts.admin')

@section('content')
<div class="content">
    @can('receipt_create')
    <div style="margin-bottom: 10px;" class="row">
        <div class="col-lg-12">
            <a class="btn btn-success" href="{{ route('admin.receipts.create') }}">
                {{ trans('global.add') }} {{ trans('cruds.receipt.title_singular') }}
            </a>
            @if (url()->current() == url('/admin/receipts/paid'))
            <a href="/admin/receipts" class="btn btn-primary pull-right">Ver não pagos</a>
            @else
            <a href="/admin/receipts/paid" class="btn btn-primary pull-right">Ver histórico dos recibos pagos</a>
            @endif
        </div>
    </div>
    @endcan
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.receipt.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    <table
                        class=" table table-bordered table-striped table-hover ajaxTable datatable datatable-Receipt">
                        <thead>
                            <tr>
                                <th width="10"></th>
                                <th>{{ trans('cruds.receipt.fields.id') }}</th>
                                <th>{{ trans('cruds.receipt.fields.driver') }}</th>
                                <th>{{ trans('cruds.receipt.fields.value') }}</th>
                                <th>IVA</th>
                                <th>RF</th>
                                <th>Valor do recibo</th>
                                <th>{{ trans('cruds.receipt.fields.file') }}</th>
                                <th>Saldo atual</th>
                                <th>{{ trans('cruds.receipt.fields.verified') }}</th>
                                <th>{{ trans('cruds.receipt.fields.amount_transferred') }}</th>
                                <th>{{ trans('cruds.receipt.fields.paid') }}</th>
                                <th>{{ trans('cruds.receipt.fields.tvde_week') }}</th>
                                <th>{{ trans('cruds.receipt.fields.created_at') }}</th>
                                <th>&nbsp;</th>
                            </tr>
                            <tr class="dt-filters">
                                <td></td>
                                <td></td>
                                <td>
                                    <select class="search">
                                        <option value>{{ trans('global.all') }}</option>
                                        @foreach($drivers as $key => $item)
                                            <option value="{{ $item->name }}">{{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td>
                                    <select class="search">
                                        <option value>{{ trans('global.all') }}</option>
                                        @foreach($tvde_weeks as $key => $item)
                                            <option value="{{ $item->start_date }}">{{ $item->start_date }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td></td>
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
{{-- FixedHeader (CSS + JS) --}}
<link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.dataTables.min.css">
<script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>

<script>
$(function () {
  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)
  @can('receipt_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.receipts.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      var ids = $.map(dt.rows({ selected: true }).data(), function (entry) { return entry.id });

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
    ajax: "/admin/receipts{{ url()->current() == url('/admin/receipts/paid') ? '/paid' : '' }}",
    columns: [
      { data: 'placeholder', name: 'placeholder' },
      { data: 'id', name: 'id' },
      { data: 'driver_name', name: 'driver.name' },
      { data: 'value', name: 'value' },
      { data: 'iva', name: 'iva' },
      { data: 'rf', name: 'rf' },
      { data: 'final', name: 'final' },
      { data: 'file', name: 'file', sortable: false, searchable: false },
      { data: 'receipt_value', name: 'receipt_value', sortable: false, searchable: false },
      { data: 'verified', name: 'verified' },
      { data: 'amount_transferred', name: 'amount_transferred'},
      { data: 'paid', name: 'paid'},
      { data: 'tvde_week_start_date', name: 'tvde_weeks.start_date' },
      { data: 'created_at', name: 'created_at' },
      { data: 'actions', name: '{{ trans('global.actions') }}' }
    ],
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  };

  // Init DataTable
  let table = $('.datatable-Receipt').DataTable(dtOverrideGlobals);

  // === FixedHeader ===
  // tenta detetar uma navbar fixa para compensar a altura
  var headerOffset = 0;
  var $fixedBars = $('.navbar-fixed-top, .main-header .navbar, .navbar').filter(function(){
      return $(this).css('position') === 'fixed';
  });
  if ($fixedBars.length) headerOffset = $fixedBars.first().outerHeight() || 0;

  new $.fn.dataTable.FixedHeader(table, {
      header: true,
      footer: false,
      headerOffset: headerOffset
  });

  // Ajustar colunas quando muda de tab (e reposicionar header fixo)
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
      table.columns.adjust().draw(false);
  });

  // === Filtros no header (funcionam no header original e no clonado) ===
  let visibleColumnsIndexes = null;

  function applyColumnFilter(inputEl){
      let strict = $(inputEl).attr('strict') || false;
      let value  = strict && inputEl.value ? "^" + inputEl.value + "$" : inputEl.value;

      // índice da coluna deste filtro
      let index = $(inputEl).closest('th').index();
      if (visibleColumnsIndexes !== null) index = visibleColumnsIndexes[index];

      table.column(index).search(value, strict).draw();
  }

  // delega eventos tanto no wrapper do DataTables como no documento (para o header clonado)
  $(document).on('input change', '.dataTables_wrapper thead .search, .FixedHeader_Cloned thead .search', function(){
      applyColumnFilter(this);
  });

  table.on('column-visibility.dt', function(){
      visibleColumnsIndexes = [];
      table.columns(':visible').every(function(colIdx){
          visibleColumnsIndexes.push(colIdx);
      });
  });

});
</script>

<script>
    checkPay = (receipt_id) => {
        if($('#verified-' + receipt_id).prop('checked') == true){
            $('#check-' + receipt_id).attr('disabled', 'true');
            $.get('/admin/receipts/checkPay/' + receipt_id).then((resp) => {
                console.log(resp);
            });
        } else {
            alert('Falta verificar o recibo.');
            $('#check-' + receipt_id).prop('checked', false);
        }
    }

    checkVerified = (receipt_id) => {
        var receipt_value = $('#receipt_value-' + receipt_id).val();
        var amount_transferred = $('#amount_transferred-' + receipt_id).val();
        if(receipt_value.length > 0 && amount_transferred.length > 0){
            $('#verified-' + receipt_id).attr('disabled', 'true');
            $.get('/admin/receipts/checkVerified/' + receipt_id + '/' + receipt_value + '/' + amount_transferred).then((resp) => {
                $('#receipt_value-' + receipt_id).attr('disabled', 'true');
                $('#amount_transferred-' + receipt_id).attr('disabled', 'true');
            });
        } else {
            alert('Valor do recibo e quantia a transferir obrigatórios.');
            $('#verified-' + receipt_id).prop('checked', false);
            $('#amount_transferred-' + receipt_id).prop('checked', false);
        }
    }
</script>
@endsection
