@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default" style="margin-top: 20px;">
        <div class="panel-heading">
            {{ trans('cruds.driversPayment.title') }}
        </div>
        <div class="panel-body">
            <table class=" table table-bordered table-striped table-hover datatable">
                <thead>
                    <tr>
                        <td></td>
                        <th>Driver</th>
                        <th>Email</th>
                        <th>IBAN</th>
                        <th>VAT</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($receipts as $receipt)
                    <tr>
                        <td></td>
                        <td>{{ $receipt->driver->name }}</td>
                        <td>{{ $receipt->driver->email }}</td>
                        <td>{{ $receipt->driver->iban }}</td>
                        <td>{{ $receipt->driver->payment_vat }}</td>
                        <td>{{ $receipt->amount_transferred }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    $(function () {
        let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons);
        let dtOverrideGlobals = {
            buttons: dtButtons,
        }
        let table = $('.datatable').DataTable(dtOverrideGlobals);
    });
</script>
@endsection

