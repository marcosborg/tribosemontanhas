<div class="form-group {{ $errors->has('driver_id') ? 'has-error' : '' }}">
    <label class="required" for="driver_id">Motorista</label>
    <select class="form-control select2" name="driver_id" id="driver_id" required>
        <option value="">{{ trans('global.pleaseSelect') }}</option>
        @foreach($drivers as $driver)
            <option value="{{ $driver->id }}" {{ (string) old('driver_id', $driverDeposit->driver_id ?? '') === (string) $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
        @endforeach
    </select>
    @if($errors->has('driver_id'))
        <span class="help-block" role="alert">{{ $errors->first('driver_id') }}</span>
    @endif
</div>

<div class="form-group {{ $errors->has('company_id') ? 'has-error' : '' }}">
    <label class="required" for="company_id">Empresa</label>
    <select class="form-control select2" name="company_id" id="company_id" required>
        <option value="">{{ trans('global.pleaseSelect') }}</option>
        @foreach($companies as $company)
            <option value="{{ $company->id }}" {{ (string) old('company_id', $driverDeposit->company_id ?? '') === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
        @endforeach
    </select>
    @if($errors->has('company_id'))
        <span class="help-block" role="alert">{{ $errors->first('company_id') }}</span>
    @endif
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group {{ $errors->has('total_amount') ? 'has-error' : '' }}">
            <label class="required" for="total_amount">Valor total da caução</label>
            <input class="form-control" type="number" name="total_amount" id="total_amount" value="{{ old('total_amount', $driverDeposit->total_amount ?? '') }}" step="0.01" required>
            @if($errors->has('total_amount'))
                <span class="help-block" role="alert">{{ $errors->first('total_amount') }}</span>
            @endif
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group {{ $errors->has('initial_payment') ? 'has-error' : '' }}">
            <label for="initial_payment">Pagamento inicial</label>
            <input class="form-control" type="number" name="initial_payment" id="initial_payment" value="{{ old('initial_payment', $driverDeposit->initial_payment ?? 0) }}" step="0.01">
            @if($errors->has('initial_payment'))
                <span class="help-block" role="alert">{{ $errors->first('initial_payment') }}</span>
            @endif
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group {{ $errors->has('weekly_amount') ? 'has-error' : '' }}">
            <label class="required" for="weekly_amount">Valor semanal</label>
            <input class="form-control" type="number" name="weekly_amount" id="weekly_amount" value="{{ old('weekly_amount', $driverDeposit->weekly_amount ?? '') }}" step="0.01" required>
            @if($errors->has('weekly_amount'))
                <span class="help-block" role="alert">{{ $errors->first('weekly_amount') }}</span>
            @endif
        </div>
    </div>
</div>

<div class="form-group {{ $errors->has('tvde_weeks') ? 'has-error' : '' }}">
    <label class="required" for="tvde_weeks">Semanas a debitar</label>
    <select class="form-control select2" name="tvde_weeks[]" id="tvde_weeks" multiple required>
        @foreach($tvdeWeeks as $week)
            <option value="{{ $week->id }}" {{ in_array($week->id, array_map('intval', $selectedWeeks ?? []), true) ? 'selected' : '' }}>
                {{ $week->start_date }} a {{ $week->end_date }}
            </option>
        @endforeach
    </select>
    @if($errors->has('tvde_weeks'))
        <span class="help-block" role="alert">{{ $errors->first('tvde_weeks') }}</span>
    @endif
</div>

<div class="form-group {{ $errors->has('status') ? 'has-error' : '' }}">
    <label class="required" for="status">Estado</label>
    <select class="form-control" name="status" id="status" required>
        @foreach($statuses as $key => $label)
            <option value="{{ $key }}" {{ old('status', $driverDeposit->status ?? \App\Models\DriverDeposit::STATUS_ACTIVE) === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    @if($errors->has('status'))
        <span class="help-block" role="alert">{{ $errors->first('status') }}</span>
    @endif
</div>

<div class="form-group {{ $errors->has('notes') ? 'has-error' : '' }}">
    <label for="notes">Notas</label>
    <textarea class="form-control" name="notes" id="notes">{{ old('notes', $driverDeposit->notes ?? '') }}</textarea>
    @if($errors->has('notes'))
        <span class="help-block" role="alert">{{ $errors->first('notes') }}</span>
    @endif
</div>

<div class="form-group">
    <button class="btn btn-danger" type="submit">{{ trans('global.save') }}</button>
</div>
