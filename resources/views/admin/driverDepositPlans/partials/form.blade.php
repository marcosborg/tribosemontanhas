<div class="row">
    <div class="col-md-6">
        <div class="form-group {{ $errors->has('driver_id') ? 'has-error' : '' }}">
            <label for="driver_id">Motorista</label>
            <select class="form-control select2" name="driver_id" id="driver_id" required>
                <option value=""></option>
                @foreach($drivers as $driver)
                    <option value="{{ $driver->id }}" {{ old('driver_id', $driverDepositPlan->driver_id ?? '') == $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group {{ $errors->has('company_id') ? 'has-error' : '' }}">
            <label for="company_id">Empresa</label>
            <select class="form-control select2" name="company_id" id="company_id" required>
                <option value=""></option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" {{ old('company_id', $driverDepositPlan->company_id ?? '') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3">
        <div class="form-group {{ $errors->has('initial_amount') ? 'has-error' : '' }}">
            <label for="initial_amount">Entrada inicial</label>
            <input class="form-control" type="number" name="initial_amount" id="initial_amount" value="{{ old('initial_amount', $driverDepositPlan->initial_amount ?? 0) }}" step="0.01" min="0">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group {{ $errors->has('weekly_amount') ? 'has-error' : '' }}">
            <label for="weekly_amount">Valor semanal</label>
            <input class="form-control" type="number" name="weekly_amount" id="weekly_amount" value="{{ old('weekly_amount', $driverDepositPlan->weekly_amount ?? '') }}" step="0.01" min="0" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group {{ $errors->has('total_weeks') ? 'has-error' : '' }}">
            <label for="total_weeks">Numero de semanas</label>
            <input class="form-control" type="number" name="total_weeks" id="total_weeks" value="{{ old('total_weeks', $driverDepositPlan->total_weeks ?? 0) }}" min="0" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group {{ $errors->has('status') ? 'has-error' : '' }}">
            <label for="status">Estado</label>
            <select class="form-control" name="status" id="status" required>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ old('status', $driverDepositPlan->status ?? \App\Models\DriverDepositPlan::STATUS_ACTIVE) === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="form-group {{ $errors->has('start_week_id') ? 'has-error' : '' }}">
    <label for="start_week_id">Semana inicial</label>
    <select class="form-control select2" name="start_week_id" id="start_week_id" required>
        <option value=""></option>
        @foreach($tvdeWeeks as $week)
            <option value="{{ $week->id }}" {{ old('start_week_id', $driverDepositPlan->start_week_id ?? '') == $week->id ? 'selected' : '' }}>
                {{ $week->start_date }} - {{ $week->end_date }}
            </option>
        @endforeach
    </select>
</div>
<div class="form-group {{ $errors->has('notes') ? 'has-error' : '' }}">
    <label for="notes">Notas</label>
    <textarea class="form-control" name="notes" id="notes">{{ old('notes', $driverDepositPlan->notes ?? '') }}</textarea>
</div>
<div class="form-group">
    <button class="btn btn-danger" type="submit">{{ trans('global.save') }}</button>
    <a class="btn btn-default" href="{{ route('admin.driver-deposit-plans.index') }}">Voltar</a>
</div>
