@extends('layouts.admin')
@section('content')
<div class="content">

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('global.show') }} {{ trans('cruds.companyExpense.title') }}
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <div class="form-group">
                            <a class="btn btn-default" href="{{ route('admin.company-expenses.index') }}">
                                {{ trans('global.back_to_list') }}
                            </a>
                        </div>
                        <table class="table table-bordered table-striped">
                            <tbody>
                                <tr>
                                    <th>
                                        {{ trans('cruds.companyExpense.fields.id') }}
                                    </th>
                                    <td>
                                        {{ $companyExpense->id }}
                                    </td>
                                </tr>
                                <tr><th>Modo</th><td>{{ $companyExpense->expense_mode === 'accounting' ? 'Contabilidade' : 'Recorrente' }}</td></tr>
                                @if($companyExpense->expense_mode === 'accounting')
                                <tr><th>Tipo de despesa</th><td>{{ App\Models\CompanyExpense::EXPENSE_TYPE_RADIO[$companyExpense->expense_type] ?? $companyExpense->expense_type }}</td></tr>
                                <tr><th>Data</th><td>{{ $companyExpense->date }}</td></tr>
                                <tr><th>Descrição</th><td>{!! $companyExpense->description !!}</td></tr>
                                <tr><th>Valor</th><td>{{ number_format($companyExpense->value, 2, ',', '.') }} €</td></tr>
                                <tr><th>Valor final</th><td>{{ $companyExpense->invoice_value !== null ? number_format($companyExpense->invoice_value, 2, ',', '.') . ' €' : '—' }}</td></tr>
                                <tr><th>IVA</th><td>{{ $companyExpense->vat }}%</td></tr>
                                <tr><th>Estado</th><td>{{ $companyExpense->is_paid ? 'Pago' : 'Por pagar' }}</td></tr>
                                <tr><th>Referência</th><td>{{ $companyExpense->payment_reference }}</td></tr>
                                <tr><th>Pagar a</th><td>{{ $companyExpense->pay_to }}</td></tr>
                                <tr><th>Documentos</th><td>@foreach($companyExpense->files as $file)<a href="{{ $file->getUrl() }}" target="_blank">{{ $file->file_name }}</a><br>@endforeach</td></tr>
                                @endif
                                <tr>
                                    <th>
                                        {{ trans('cruds.companyExpense.fields.name') }}
                                    </th>
                                    <td>
                                        {{ $companyExpense->name }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.companyExpense.fields.company') }}
                                    </th>
                                    <td>
                                        {{ $companyExpense->company->name ?? '' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.companyExpense.fields.weekly_value') }}
                                    </th>
                                    <td>
                                        {{ $companyExpense->weekly_value }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.companyExpense.fields.start_date') }}
                                    </th>
                                    <td>
                                        {{ $companyExpense->start_date }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.companyExpense.fields.end_date') }}
                                    </th>
                                    <td>
                                        {{ $companyExpense->end_date }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.companyExpense.fields.qty') }}
                                    </th>
                                    <td>
                                        {{ $companyExpense->qty }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="form-group">
                            <a class="btn btn-default" href="{{ route('admin.company-expenses.index') }}">
                                {{ trans('global.back_to_list') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>



        </div>
    </div>
</div>
@endsection
