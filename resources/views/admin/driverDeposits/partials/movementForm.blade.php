@php($fieldSuffix = preg_replace('/[^A-Za-z0-9_-]/', '_', $button))

<div class="form-group {{ $errors->has('tvde_week_id') ? 'has-error' : '' }}">
    <label class="required" for="tvde_week_id_{{ $fieldSuffix }}">Semana</label>
    <select class="form-control select2" name="tvde_week_id" id="tvde_week_id_{{ $fieldSuffix }}" required>
        @foreach($tvdeWeeks as $week)
            <option value="{{ $week->id }}" {{ (string) old('tvde_week_id', $defaultWeekId ?? '') === (string) $week->id ? 'selected' : '' }}>
                {{ $week->start_date }} a {{ $week->end_date }}
            </option>
        @endforeach
    </select>
    @if($errors->has('tvde_week_id'))
        <span class="help-block" role="alert">{{ $errors->first('tvde_week_id') }}</span>
    @endif
</div>
<div class="form-group {{ $errors->has('amount') ? 'has-error' : '' }}">
    <label class="required" for="amount_{{ $fieldSuffix }}">Valor</label>
    <input class="form-control" type="number" name="amount" id="amount_{{ $fieldSuffix }}" value="{{ old('amount', $defaultAmount) }}" step="0.01" required>
    @if($errors->has('amount'))
        <span class="help-block" role="alert">{{ $errors->first('amount') }}</span>
    @endif
</div>
<div class="form-group {{ $errors->has('description') ? 'has-error' : '' }}">
    <label for="description_{{ $fieldSuffix }}">Descrição</label>
    <input class="form-control" type="text" name="description" id="description_{{ $fieldSuffix }}" value="{{ old('description', '') }}">
    @if($errors->has('description'))
        <span class="help-block" role="alert">{{ $errors->first('description') }}</span>
    @endif
</div>
<button class="btn btn-danger" type="submit">{{ $button }}</button>
