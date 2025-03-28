@extends('layouts.admin')
@section('content')
<div class="content">
    @can('expense_receipt_create')
        <div style="margin-bottom: 10px;" class="row">
            <div class="col-lg-12">
                <a class="btn btn-success" href="{{ route('admin.expense-receipts.create') }}">
                    {{ trans('global.add') }} {{ trans('cruds.expenseReceipt.title_singular') }}
                </a>
            </div>
        </div>
    @endcan
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.expenseReceipt.title_singular') }} {{ trans('global.list') }}
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class=" table table-bordered table-striped table-hover datatable datatable-ExpenseReceipt">
                            <thead>
                                <tr>
                                    <th width="10">

                                    </th>
                                    <th>
                                        {{ trans('cruds.expenseReceipt.fields.id') }}
                                    </th>
                                    <th>
                                        {{ trans('cruds.expenseReceipt.fields.driver') }}
                                    </th>
                                    <th>
                                        {{ trans('cruds.expenseReceipt.fields.tvde_week') }}
                                    </th>
                                    <th>
                                        {{ trans('cruds.expenseReceipt.fields.receipts') }}
                                    </th>
                                    <th>
                                        {{ trans('cruds.expenseReceipt.fields.approved_value') }}
                                    </th>
                                    <th>
                                        {{ trans('cruds.expenseReceipt.fields.verified') }}
                                    </th>
                                    <th>
                                        &nbsp;
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($expenseReceipts as $key => $expenseReceipt)
                                    <tr data-entry-id="{{ $expenseReceipt->id }}">
                                        <td>

                                        </td>
                                        <td>
                                            {{ $expenseReceipt->id ?? '' }}
                                        </td>
                                        <td>
                                            {{ $expenseReceipt->driver->name ?? '' }}
                                        </td>
                                        <td>
                                            {{ $expenseReceipt->tvde_week->start_date ?? '' }}
                                        </td>
                                        <td>
                                            @foreach($expenseReceipt->receipts as $key => $media)
                                                <a href="{{ $media->getUrl() }}" target="_blank">
                                                    {{ trans('global.view_file') }}
                                                </a>
                                            @endforeach
                                        </td>
                                        <td>
                                            {{ $expenseReceipt->approved_value ?? '' }}
                                        </td>
                                        <td>
                                            <span style="display:none">{{ $expenseReceipt->verified ?? '' }}</span>
                                            <input type="checkbox" disabled="disabled" {{ $expenseReceipt->verified ? 'checked' : '' }}>
                                        </td>
                                        <td>
                                            @can('expense_receipt_show')
                                                <a class="btn btn-xs btn-primary" href="{{ route('admin.expense-receipts.show', $expenseReceipt->id) }}">
                                                    {{ trans('global.view') }}
                                                </a>
                                            @endcan

                                            @can('expense_receipt_edit')
                                                <a class="btn btn-xs btn-info" href="{{ route('admin.expense-receipts.edit', $expenseReceipt->id) }}">
                                                    {{ trans('global.edit') }}
                                                </a>
                                            @endcan

                                            @can('expense_receipt_delete')
                                                <form action="{{ route('admin.expense-receipts.destroy', $expenseReceipt->id) }}" method="POST" onsubmit="return confirm('{{ trans('global.areYouSure') }}');" style="display: inline-block;">
                                                    <input type="hidden" name="_method" value="DELETE">
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                    <input type="submit" class="btn btn-xs btn-danger" value="{{ trans('global.delete') }}">
                                                </form>
                                            @endcan

                                        </td>

                                    </tr>
                                @endforeach
                            </tbody>
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
@can('expense_receipt_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}'
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.expense-receipts.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      var ids = $.map(dt.rows({ selected: true }).nodes(), function (entry) {
          return $(entry).data('entry-id')
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

  $.extend(true, $.fn.dataTable.defaults, {
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  });
  let table = $('.datatable-ExpenseReceipt:not(.ajaxTable)').DataTable({ buttons: dtButtons })
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(e){
      $($.fn.dataTable.tables(true)).DataTable()
          .columns.adjust();
  });
  
})

</script>
@endsection