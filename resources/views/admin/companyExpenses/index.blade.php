@extends('layouts.admin')
@section('content')
<div class="content">
    @can('company_expense_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                @if($accountingReady)
                <a class="btn btn-success" href="{{ route('admin.company-expenses.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.companyExpense.title_singular') }}
                </a>
                <button class="btn btn-warning" data-toggle="modal" data-target="#csvImportModal">{{ trans('global.app_csvImport') }}</button>
                <button class="btn btn-info" data-toggle="modal" data-target="#accountingImportModal">Importar contabilidade</button>
                @endif
                @include('csvImport.modal', ['model' => 'CompanyExpense', 'route' => 'admin.company-expenses.parseCsvImport'])
            </div>
        </div>
    @endcan
    @if($accountingReady)
    @can('company_expense_create')
    <div class="modal fade" id="accountingImportModal" tabindex="-1" role="dialog">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Importar despesas da contabilidade</h4></div>
            <div class="modal-body"><form method="POST" action="{{ route('admin.company-expenses.importAccounting') }}" enctype="multipart/form-data">@csrf
                <div class="form-group {{ $errors->has('accounting_file') ? 'has-error' : '' }}"><label for="accounting_file">Ficheiro</label><input class="form-control" type="file" name="accounting_file" id="accounting_file" accept=".csv,.txt,.xls,.xlsx" required>
                    @if($errors->has('accounting_file'))<span class="help-block">{{ $errors->first('accounting_file') }}</span>@endif
                    <span class="help-block">Obrigatórias: Data, Descrição Banco, Valor e Tipo/nt. Empresa é obrigatória apenas quando não está selecionada no topo. Opcionais: IVA e Valor final.</span>
                </div><button class="btn btn-primary" type="submit">Importar</button>
            </form></div>
        </div></div>
    </div>
    @endcan
    @endif
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.companyExpense.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    @if(!$accountingReady)
                        <div class="alert alert-warning">A atualização contabilística está preparada, mas aguarda a execução da migration no servidor. A listagem histórica permanece disponível.</div>
                    @endif
                    @if(session('companyExpenseImportReport'))
                        @php($report = session('companyExpenseImportReport'))
                        <div class="alert alert-info"><strong>Relatório de importação:</strong> {{ $report['imported'] ?? 0 }} importadas, {{ count($report['failed'] ?? []) }} falhadas.
                        @if(!empty($report['failed']))<div class="table-responsive"><table class="table table-bordered table-condensed"><thead><tr><th>Linha</th><th>Empresa</th><th>Tipo</th><th>Valor</th><th>Motivo</th></tr></thead><tbody>
                            @foreach($report['failed'] as $row)<tr><td>{{ $row['line'] }}</td><td>{{ $row['company'] }}</td><td>{{ $row['expense_type'] }}</td><td>{{ $row['value'] }}</td><td>{{ $row['reason'] }}</td></tr>@endforeach
                        </tbody></table></div>@endif</div>
                    @endif
                    <div class="alert alert-warning">Despesas contabilísticas por pagar: <strong>{{ $unpaidCount }}</strong></div>
                    <table class=" table table-bordered table-striped table-hover ajaxTable datatable datatable-CompanyExpense">
                        <thead>
                            <tr>
                                <th width="10">

                                </th>
                                <th>
                                    {{ trans('cruds.companyExpense.fields.id') }}
                                </th>
                                <th>Modo / tipo</th>
                                <th>
                                    {{ trans('cruds.companyExpense.fields.company') }}
                                </th>
                                <th>Modo</th>
                                <th>Data / período</th>
                                <th>Estado</th>
                                <th>Documentos</th>
                                <th>Valor</th>
                                <th>
                                    &nbsp;
                                </th>
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
  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)
@can('company_expense_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}';
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.company-expenses.massDestroy') }}",
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
    ajax: "{{ route('admin.company-expenses.index') }}",
    columns: [
      { data: 'placeholder', name: 'placeholder' },
{ data: 'id', name: 'id' },
{ data: 'name', name: 'expense_type' },
{ data: 'company_name', name: 'company.name' },
{ data: 'expense_mode', name: 'expense_mode' },
{ data: 'date', name: 'date' },
{ data: 'is_paid', name: 'is_paid' },
{ data: 'files', name: 'files', orderable: false, searchable: false },
{ data: 'weekly_value', name: 'value' },
{ data: 'actions', name: '{{ trans('global.actions') }}' }
    ],
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  };
  let table = $('.datatable-CompanyExpense').DataTable(dtOverrideGlobals);
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(e){
      $($.fn.dataTable.tables(true)).DataTable()
          .columns.adjust();
  });
  
});

</script>
@endsection
