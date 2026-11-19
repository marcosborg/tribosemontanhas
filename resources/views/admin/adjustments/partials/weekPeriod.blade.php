@php
    $periodStart = isset($adjustment) ? $adjustment->getRawOriginal('start_date') : null;
    $periodEnd = isset($adjustment) ? $adjustment->getRawOriginal('end_date') : null;
    $weekStart = old('week_start', $periodStart ? \Carbon\Carbon::parse($periodStart)->startOfWeek()->format('Y-m-d') : now()->startOfWeek()->format('Y-m-d'));
    $weekCount = old('week_count', $periodStart && $periodEnd ? max(1, (int) ceil((\Carbon\Carbon::parse($periodStart)->diffInDays(\Carbon\Carbon::parse($periodEnd)) + 1) / 7)) : 1);
@endphp
<input type="hidden" name="weekly_schedule" id="weekly_schedule" value="{{ old('weekly_schedule', isset($adjustment) ? 0 : 1) }}">
<input type="hidden" name="start_date" value="{{ old('start_date', $adjustment->start_date ?? '') }}">
<input type="hidden" name="end_date" value="{{ old('end_date', $adjustment->end_date ?? '') }}">
<div class="form-group {{ $errors->has('week_start') ? 'has-error' : '' }}">
    <label for="choose-week">Semana de início</label>
    <input type="hidden" name="week_start" id="week_start" value="{{ $weekStart }}">
    <button type="button" id="choose-week" class="btn btn-default form-control" aria-expanded="false" aria-controls="week-calendar">Escolher semana</button>
    <div id="week-calendar" hidden>
        <div class="week-calendar-header"><button type="button" id="previous-week-month" aria-label="Mês anterior">‹</button><strong id="week-calendar-month"></strong><button type="button" id="next-week-month" aria-label="Mês seguinte">›</button></div>
        <div class="week-calendar-days" aria-hidden="true"><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span><span>Dom</span></div>
        <div id="week-calendar-rows"></div>
    </div>
    @error('week_start')<span class="help-block">{{ $message }}</span>@enderror
</div>
<div class="form-group {{ $errors->has('week_count') ? 'has-error' : '' }}">
    <label for="week_count">Número de semanas</label>
    <input class="form-control" type="number" name="week_count" id="week_count" value="{{ $weekCount }}" min="1" max="520" step="1" required>
    <span class="help-block">O mesmo valor é aplicado em cada semana.</span>
    @error('week_count')<span class="help-block">{{ $message }}</span>@enderror
</div>
<div class="form-group"><label>Período do ajuste</label><p id="week-period-summary" role="status" data-existing="{{ isset($adjustment) ? ($adjustment->start_date ?: 'Sem início definido') . ' a ' . ($adjustment->end_date ?: 'Sem fim definido') : '' }}"></p></div>
