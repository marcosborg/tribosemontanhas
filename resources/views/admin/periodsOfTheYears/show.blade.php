@extends('layouts.admin')
@section('content')
<div class="content">

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('global.show') }} {{ trans('cruds.periodsOfTheYear.title') }}
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <div class="form-group">
                            <a class="btn btn-default" href="{{ route('admin.periods-of-the-years.index') }}">
                                {{ trans('global.back_to_list') }}
                            </a>
                        </div>
                        <table class="table table-bordered table-striped">
                            <tbody>
                                <tr>
                                    <th>
                                        {{ trans('cruds.periodsOfTheYear.fields.id') }}
                                    </th>
                                    <td>
                                        {{ $periodsOfTheYear->id }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.periodsOfTheYear.fields.start_date') }}
                                    </th>
                                    <td>
                                        {{ $periodsOfTheYear->start_date }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.periodsOfTheYear.fields.end_date') }}
                                    </th>
                                    <td>
                                        {{ $periodsOfTheYear->end_date }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        {{ trans('cruds.periodsOfTheYear.fields.type') }}
                                    </th>
                                    <td>
                                        {{ App\Models\PeriodsOfTheYear::TYPE_RADIO[$periodsOfTheYear->type] ?? '' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="form-group">
                            <a class="btn btn-default" href="{{ route('admin.periods-of-the-years.index') }}">
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