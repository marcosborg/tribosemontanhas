@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Registar movimento real de caucao</div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.driver-deposit-real-movements.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group"><label>Motorista</label><select class="form-control select2" name="driver_id" required><option value=""></option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" {{ old('driver_id') == $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>@endforeach</select></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group"><label>Empresa</label><select class="form-control select2" name="company_id" required><option value=""></option>@foreach($companies as $company)<option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>@endforeach</select></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group"><label>Tipo</label><select class="form-control" name="type" required>@foreach($types as $key => $label)<option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group"><label>Valor</label><input class="form-control" type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required></div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group"><label>Metodo pagamento</label><input class="form-control" type="text" name="payment_method" value="{{ old('payment_method') }}"></div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group"><label>Semana</label><select class="form-control select2" name="tvde_week_id"><option value=""></option>@foreach($tvdeWeeks as $week)<option value="{{ $week->id }}" {{ old('tvde_week_id') == $week->id ? 'selected' : '' }}>{{ $week->start_date }} - {{ $week->end_date }}</option>@endforeach</select></div>
                    </div>
                </div>
                <div class="form-group"><label>Descricao</label><textarea class="form-control" name="description">{{ old('description') }}</textarea></div>
                <button class="btn btn-danger" type="submit">{{ trans('global.save') }}</button>
                <a class="btn btn-default" href="{{ route('admin.driver-deposit-real-movements.index') }}">Voltar</a>
            </form>
        </div>
    </div>
</div>
@endsection
