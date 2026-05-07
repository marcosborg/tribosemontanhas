@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('global.show') }} {{ trans('cruds.vehicleExpense.title') }}
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <div class="form-group">
                            <a class="btn btn-default" href="{{ route('admin.vehicle-expenses.index') }}">
                                {{ trans('global.back_to_list') }}
                            </a>
                        </div>
                        <table class="table table-bordered table-striped">
                            <tbody>
                                <tr>
                                    <th>{{ trans('cruds.vehicleExpense.fields.id') }}</th>
                                    <td>{{ $vehicleExpense->id }}</td>
                                </tr>
                                <tr>
                                    <th>{{ trans('cruds.vehicleExpense.fields.vehicle_item') }}</th>
                                    <td>{{ $vehicleExpense->vehicle_item->license_plate ?? '' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ trans('cruds.vehicleExpense.fields.expense_type') }}</th>
                                    <td>{{ App\Models\VehicleExpense::EXPENSE_TYPE_RADIO[$vehicleExpense->expense_type] ?? $vehicleExpense->expense_type ?? '' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ trans('cruds.vehicleExpense.fields.date') }}</th>
                                    <td>{{ $vehicleExpense->date }}</td>
                                </tr>
                                <tr>
                                    <th>{{ trans('cruds.vehicleExpense.fields.description') }}</th>
                                    <td>{!! $vehicleExpense->description !!}</td>
                                </tr>
                                <tr>
                                    <th>{{ trans('cruds.vehicleExpense.fields.files') }}</th>
                                    <td>
                                        @foreach($vehicleExpense->files as $key => $media)
                                            <a href="{{ $media->getUrl() }}" target="_blank">
                                                {{ trans('global.view_file') }}
                                            </a>
                                        @endforeach
                                    </td>
                                </tr>
                                <tr>
                                    <th>Estado</th>
                                    <td>{{ $vehicleExpense->is_paid ? 'Pago' : 'Por pagar' }}</td>
                                </tr>
                                <tr>
                                    <th>Pago em</th>
                                    <td>{{ $vehicleExpense->paid_at ? $vehicleExpense->paid_at->format('Y-m-d H:i:s') : '' }}</td>
                                </tr>
                                <tr>
                                    <th>Referencia pagamento</th>
                                    <td>{{ $vehicleExpense->payment_reference ?? '' }}</td>
                                </tr>
                                @if($vehicleExpense->group_uuid)
                                <tr>
                                    <th>Grupo</th>
                                    <td>
                                        {{ $vehicleExpense->group_label ?: 'Despesa em grupo' }}
                                        <br>
                                        <small>{{ $vehicleExpense->group_uuid }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Outras despesas do grupo</th>
                                    <td>
                                        @forelse($groupExpenses as $groupExpense)
                                            <a href="{{ route('admin.vehicle-expenses.show', $groupExpense->id) }}">
                                                #{{ $groupExpense->id }} - {{ $groupExpense->vehicle_item->license_plate ?? '' }} - {{ number_format((float) $groupExpense->value, 2, ',', '.') }} €
                                            </a>
                                            @if(!$loop->last)<br>@endif
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <th>{{ trans('cruds.vehicleExpense.fields.value') }}</th>
                                    <td>{{ $vehicleExpense->value }}</td>
                                </tr>
                                <tr>
                                    <th>{{ trans('cruds.vehicleExpense.fields.vat') }}</th>
                                    <td>{{ $vehicleExpense->vat }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="form-group">
                            <a class="btn btn-default" href="{{ route('admin.vehicle-expenses.index') }}">
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
