@php
    $items = $driverDeposit ? $driverDeposit->plan->items()->with(['movements', 'tvde_week'])->get() : collect();
    $initialRows = old('initial_payments', session()->hasOldInput('total_amount') ? [] : $items->where('kind', 'initial')->map(function ($item) {
        $movement = $item->movements->firstWhere('type', 'payment');
        return ['id' => $item->id, 'amount' => $item->amount, 'payment_date' => optional($movement->payment_date)->format('Y-m-d'), 'payment_method' => $movement->payment_method];
    })->values()->all());
    $weeklyRows = old('weekly_payments', session()->hasOldInput('total_amount') ? [] : $items->where('kind', 'weekly')->map(fn ($item) => ['id' => $item->id, 'amount' => $item->amount, 'tvde_week_id' => $item->tvde_week_id])->values()->all());
    $locked = $items->filter(fn ($item) => $item->kind === 'initial' || $item->paid_amount > 0 || app(\App\Services\DriverDepositInstallmentService::class)->validatedWeek($driverDeposit, $item->tvde_week_id))->pluck('id')->all();
@endphp
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="row">
    <div class="col-md-6 form-group"><label for="driver_id">Motorista</label>
        <select class="form-control select2" id="driver_id" name="driver_id" required>
            <option value="">Selecione por favor</option>
            @foreach($drivers as $driver)<option value="{{ $driver->id }}" {{ old('driver_id', $driverDeposit->driver_id ?? '') == $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-6 form-group"><label for="company_id">Empresa</label>
        <select class="form-control select2" id="company_id" name="company_id" required>
            <option value="">Selecione por favor</option>
            @foreach($companies as $company)<option value="{{ $company->id }}" {{ old('company_id', $driverDeposit->company_id ?? '') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>@endforeach
        </select>
    </div>
</div>
<div class="form-group"><label for="total_amount">Valor total da caução (€)</label>
    <input class="form-control" id="total_amount" name="total_amount" type="number" min="0.01" step="0.01" value="{{ old('total_amount', $driverDeposit->total_amount ?? '') }}" required>
</div>
<h4>Pagamentos iniciais</h4>
<p>Registe cada entrada já recebida, com a respetiva data e método de pagamento.</p>
<div class="table-responsive"><table class="table table-bordered" id="initial-payments">
    <thead><tr><th>Valor (€)</th><th>Data</th><th>Método de pagamento</th><th>Ações</th></tr></thead>
    <tbody>@foreach($initialRows as $index => $row)
        @include('admin.driverDeposits.partials.installmentRow', ['kind' => 'initial', 'lockedRow' => in_array($row['id'] ?? null, $locked)])
    @endforeach</tbody>
</table></div>
<button class="btn btn-default" type="button" data-add-payment="initial">Adicionar pagamento inicial</button>
<h4>Planeamento semanal</h4>
<p>Indique o valor a descontar em cada Semana TVDE.</p>
<div class="table-responsive"><table class="table table-bordered" id="weekly-payments">
    <thead><tr><th>Valor (€)</th><th>Semana TVDE</th><th>Ações</th></tr></thead>
    <tbody>@foreach($weeklyRows as $index => $row)
        @include('admin.driverDeposits.partials.installmentRow', ['kind' => 'weekly', 'lockedRow' => in_array($row['id'] ?? null, $locked)])
    @endforeach</tbody>
</table></div>
<button class="btn btn-default" type="button" data-add-payment="weekly">Adicionar prestação semanal</button>
<div class="alert alert-info" style="margin-top:20px" aria-live="polite" id="deposit-totals"></div>
<div class="form-group"><label for="status">Estado</label><select class="form-control" id="status" name="status">
    @foreach($statuses as $value => $label)<option value="{{ $value }}" {{ old('status', $driverDeposit->status ?? 'active') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach
</select><p class="help-block">A conclusão é calculada pelos recebimentos. Fechar cancela as prestações futuras.</p></div>
<div class="form-group"><label for="notes">Notas</label><textarea class="form-control" name="notes" id="notes">{{ old('notes', $driverDeposit->notes ?? '') }}</textarea></div>
<button class="btn btn-danger" type="submit">Gravar</button>
@foreach(['initial', 'weekly'] as $kind)
    <template id="{{ $kind }}-payment-template">
        @include('admin.driverDeposits.partials.installmentRow', ['row' => [], 'index' => '__INDEX__', 'lockedRow' => false])
    </template>
@endforeach
<script>
document.addEventListener('DOMContentLoaded', function () {
    const counters = {initial: {{ count($initialRows) ? max(array_keys($initialRows)) + 1 : 0 }}, weekly: {{ count($weeklyRows) ? max(array_keys($weeklyRows)) + 1 : 0 }}};
    const money = value => new Intl.NumberFormat('pt-PT', {style: 'currency', currency: 'EUR'}).format(value / 100);
    const cents = value => Math.round((Number(value) || 0) * 100);
    function totals() {
        const sum = kind => Array.from(document.querySelectorAll('#' + kind + '-payments input[data-amount]')).reduce((total, input) => total + cents(input.value), 0);
        const initial = sum('initial'), weekly = sum('weekly');
        const difference = cents(document.getElementById('total_amount').value) - initial - weekly;
        document.getElementById('deposit-totals').textContent = 'Inicial recebido: ' + money(initial) + ' · Semanal planeado: ' + money(weekly) + ' · Diferença: ' + money(difference);
    }
    document.querySelectorAll('[data-add-payment]').forEach(button => button.addEventListener('click', function () {
        const kind = this.dataset.addPayment;
        const body = document.querySelector('#' + kind + '-payments tbody');
        body.insertAdjacentHTML('beforeend', document.getElementById(kind + '-payment-template').innerHTML.replace(/__INDEX__/g, counters[kind]++));
        if (window.jQuery && jQuery.fn.select2) jQuery(body.lastElementChild).find('.week-select').select2({width: '100%'});
        totals();
    }));
    document.querySelectorAll('#initial-payments, #weekly-payments').forEach(table => {
        table.addEventListener('input', totals);
        table.addEventListener('click', event => { if (event.target.matches('[data-remove-payment]')) { event.target.closest('tr').remove(); totals(); } });
    });
    if (window.jQuery && jQuery.fn.select2) jQuery('.week-select').select2({width: '100%'});
    document.getElementById('total_amount').addEventListener('input', totals);
    totals();
});
</script>
