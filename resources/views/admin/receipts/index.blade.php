@extends('layouts.admin')

@section('content')
<div class="content">
    @can('receipt_create')
        <div class="row" style="margin-bottom:10px;">
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
                    <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-Receipt" style="width:100%">
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
                        {{-- Filtros por coluna (apenas onde o servidor suporta) --}}
                        <tr class="dt-filters">
                            <th></th>
                            <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>

                            {{-- Driver --}}
                            <th>
                                <select class="search form-control input-sm" strict="true">
                                    <option value="">{{ trans('global.all') }}</option>
                                    @foreach($drivers as $d)
                                        <option value="{{ $d->name }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </th>

                            {{-- Value --}}
                            <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>

                            {{-- NÃO pesquisáveis --}}
                            <th class="text-muted">&mdash;</th>
                            <th class="text-muted">&mdash;</th>
                            <th class="text-muted">&mdash;</th>
                            <th class="text-muted">&mdash;</th>
                            <th class="text-muted">&mdash;</th>

                            {{-- Verified --}}
                            <th>
                                <select class="search form-control input-sm" strict="true">
                                    <option value="">{{ trans('global.all') }}</option>
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
                                </select>
                            </th>

                            {{-- Amount transferred --}}
                            <th><input class="search form-control input-sm" type="text" placeholder="{{ trans('global.search') }}"></th>

                            {{-- Paid --}}
                            <th>
                                <select class="search form-control input-sm" strict="true">
                                    <option value="">{{ trans('global.all') }}</option>
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
                                </select>
                            </th>

                            {{-- Semana (start_date) --}}
                            <th>
                                <select class="search form-control input-sm" strict="true">
                                    <option value="">{{ trans('global.all') }}</option>
                                    @foreach($tvde_weeks as $w)
                                        <option value="{{ $w->start_date }}">{{ $w->start_date }}</option>
                                    @endforeach
                                </select>
                            </th>

                            {{-- created_at --}}
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
(function($){

  // Mostra erros do DataTables em vez de ficar “A processar…”
  $.fn.dataTable.ext.errMode = 'none';

  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons);
  @can('receipt_delete')
  dtButtons.push({
    text: '{{ trans('global.datatables.delete') }}',
    url: "{{ route('admin.receipts.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      var ids = $.map(dt.rows({ selected: true }).data(), function (entry) { return entry.id });
      if (ids.length === 0) { alert('{{ trans('global.datatables.zero_selected') }}'); return; }
      if (confirm('{{ trans('global.areYouSure') }}')) {
        $.ajax({
          headers: {'x-csrf-token': _token},
          method: 'POST',
          url: config.url,
          data: { ids: ids, _method: 'DELETE' }
        }).done(function () { location.reload() })
      }
    }
  });
  @endcan

  const table = $('.datatable-Receipt').DataTable({
    buttons     : dtButtons,
    processing  : true,
    serverSide  : true,
    retrieve    : true,
    aaSorting   : [],
    ajax: {
      url: "/admin/receipts{{ url()->current() == url('/admin/receipts/paid') ? '/paid' : '' }}",
      type: 'GET',
      error: function(xhr){
        // ajuda a diagnosticar quando “fica a processar”
        let msg = 'Erro ao carregar dados.';
        try { msg += '\n\n' + (xhr.responseJSON?.message || xhr.responseText || ''); } catch(e){}
        alert(msg);
      }
    },
    columns: [
      { data: 'placeholder',          name: 'placeholder',               orderable:false, searchable:false },
      { data: 'id',                    name: 'receipts.id' },
      { data: 'driver_name',           name: 'driver_name',              orderable:false, searchable:true  }, // controller: filterColumn('driver_name', ...)
      { data: 'value',                 name: 'receipts.value' },

      // calculadas / HTML -> não pesquisar/ordenar
      { data: 'iva',                   name: 'iva',                      orderable:false, searchable:false },
      { data: 'rf',                    name: 'rf',                       orderable:false, searchable:false },
      { data: 'final',                 name: 'final',                    orderable:false, searchable:false },
      { data: 'file',                  name: 'file',                     orderable:false, searchable:false },
      { data: 'receipt_value',         name: 'receipt_value',            orderable:false, searchable:false },

      // booleanos (controller faz filterColumn)
      { data: 'verified',              name: 'verified',                 orderable:false, searchable:true  },
      { data: 'amount_transferred',    name: 'receipts.amount_transferred' },
      { data: 'paid',                  name: 'paid',                     orderable:false, searchable:true  },

      { data: 'tvde_week_start_date',  name: 'tvde_week_start_date',     searchable:true  }, // controller: filterColumn(...)
      { data: 'created_at',            name: 'receipts.created_at' },
      { data: 'actions',               name: 'actions',                  orderable:false, searchable:false }
    ],
    orderCellsTop: true,
    order: [[1,'desc']],
    pageLength: 100,
    language: {
      processing: 'A processar…'
    }
  });

  // Se o servidor devolver erro, mostra aviso
  table.on('error.dt', function (e, settings, techNote, message) {
    alert('DataTables error: ' + message);
  });

  // === Filtros por coluna (linha “dt-filters”) ===
  let visibleColumnsIndexes = null;

  function applyColumnFilter(inputEl){
      const strict = $(inputEl).attr('strict') || false;
      const raw    = inputEl.value;
      const value  = strict && raw !== '' ? '^' + raw + '$' : raw;

      let index = $(inputEl).closest('th').index();
      if (visibleColumnsIndexes !== null) index = visibleColumnsIndexes[index];

      // NOTA: só adicionámos campos de filtro nas colunas pesquisáveis.
      table.column(index).search(value, !!strict, false).draw();
  }

  $('.datatable-Receipt thead').on('input change', '.search', function(){
      applyColumnFilter(this);
  });

  table.on('column-visibility.dt', function(){
      visibleColumnsIndexes = [];
      table.columns(':visible').every(function(colIdx){
          visibleColumnsIndexes.push(colIdx);
      });
  });

  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(){
      table.columns.adjust().draw(false);
  });

})(jQuery);
</script>

{{-- Ações existentes --}}
<script>
function checkPay (receipt_id) {
  if($('#verified-' + receipt_id).prop('checked') == true){
      $('#check-' + receipt_id).attr('disabled', 'true');
      $.get('/admin/receipts/checkPay/' + receipt_id);
  } else {
      alert('Falta verificar o recibo.');
      $('#check-' + receipt_id).prop('checked', false);
  }
}

function checkVerified (receipt_id) {
  var receipt_value = $('#receipt_value-' + receipt_id).val();
  var amount_transferred = $('#amount_transferred-' + receipt_id).val();
  if(receipt_value.length > 0 && amount_transferred.length > 0){
      $('#verified-' + receipt_id).attr('disabled', 'true');
      $.get('/admin/receipts/checkVerified/' + receipt_id + '/' + receipt_value + '/' + amount_transferred)
        .then(() => {
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
