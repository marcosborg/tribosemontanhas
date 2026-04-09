@extends('layouts.admin')
@section('content')
<div class="content">

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('global.show') }} {{ trans('cruds.teslaCharging.title') }}
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <div class="form-group">
                            <a class="btn btn-default" href="{{ route('admin.tesla-chargings.index') }}">
                                {{ trans('global.back_to_list') }}
                            </a>
                        </div>
                        <table class="table table-bordered table-striped">
                            <tbody>
                                <tr>
                                    <th>
                                        {{ trans('cruds.teslaCharging.fields.id') }}
                                    </th>
                                    <td>
                                        {{ $teslaCharging->id }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.teslaCharging.fields.value') }}
                                    </th>
                                    <td>
                                        {{ $teslaCharging->value }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.teslaCharging.fields.license') }}
                                    </th>
                                    <td>
                                        {{ $teslaCharging->license ?? '' }}
                                        @if(!empty($validation['resolved_vehicle_license_plate']))
                                            / {{ $validation['resolved_vehicle_license_plate'] }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.teslaCharging.fields.tvde_week') }}
                                    </th>
                                    <td>
                                        {{ $teslaCharging->tvde_week->start_date ?? '' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Condutor</th>
                                    <td>{{ $validation['resolved_driver_name'] ?? 'Não resolvido' }}</td>
                                </tr>
                                <tr>
                                    <th>Existe</th>
                                    <td>{{ ($validation['validation_status'] ?? '') === 'exists' ? 'Sim' : 'Não' }}</td>
                                </tr>
                                <tr>
                                    <th>Validação</th>
                                    <td>{{ $validation['validation_issue'] ?? 'Válido' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="form-group">
                            <a class="btn btn-default" href="{{ route('admin.tesla-chargings.index') }}">
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
