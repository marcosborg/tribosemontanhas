@extends('layouts.admin')
@section('content')
<div class="content">

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('global.edit') }} {{ trans('cruds.carTrack.title_singular') }}
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route("admin.car-tracks.update", [$carTrack->id]) }}" enctype="multipart/form-data">
                        @method('PUT')
                        @csrf
                        <div class="form-group {{ $errors->has('date') ? 'has-error' : '' }}">
                            <label for="date">{{ trans('cruds.carTrack.fields.date') }}</label>
                            <input class="form-control datetime" type="text" name="date" id="date" value="{{ old('date', $carTrack->date) }}">
                            @if($errors->has('date'))
                                <span class="help-block" role="alert">{{ $errors->first('date') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.carTrack.fields.date_helper') }}</span>
                        </div>
                        <div class="form-group {{ $errors->has('license_plate') ? 'has-error' : '' }}">
                            <label for="license_plate">{{ trans('cruds.carTrack.fields.license_plate') }}</label>
                            <input class="form-control" type="text" name="license_plate" id="license_plate" value="{{ old('license_plate', $carTrack->license_plate) }}">
                            @if($errors->has('license_plate'))
                                <span class="help-block" role="alert">{{ $errors->first('license_plate') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.carTrack.fields.license_plate_helper') }}</span>
                        </div>
                        <div class="form-group {{ $errors->has('value') ? 'has-error' : '' }}">
                            <label for="value">{{ trans('cruds.carTrack.fields.value') }}</label>
                            <input class="form-control" type="number" name="value" id="value" value="{{ old('value', $carTrack->value) }}" step="0.01">
                            @if($errors->has('value'))
                                <span class="help-block" role="alert">{{ $errors->first('value') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.carTrack.fields.value_helper') }}</span>
                        </div>
                        <div class="form-group">
                            <button class="btn btn-danger" type="submit">
                                {{ trans('global.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>



        </div>
    </div>
</div>
@endsection