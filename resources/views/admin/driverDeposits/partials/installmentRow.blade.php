@php($prefix = ($kind === 'initial' ? 'initial_payments' : 'weekly_payments') . '[' . $index . ']')
<tr>
    <td>
        @if(!empty($row['id']))<input type="hidden" name="{{ $prefix }}[id]" value="{{ $row['id'] }}">@endif
        <input aria-label="Valor em euros" class="form-control" data-amount type="number" min="0.01" step="0.01" name="{{ $prefix }}[amount]" value="{{ $row['amount'] ?? '' }}" required {{ $lockedRow ? 'readonly' : '' }}>
    </td>
    @if($kind === 'initial')
        <td><input aria-label="Data de pagamento" class="form-control" type="date" name="{{ $prefix }}[payment_date]" value="{{ $row['payment_date'] ?? '' }}" required {{ $lockedRow ? 'readonly' : '' }}></td>
        <td><input aria-label="Método de pagamento" class="form-control" type="text" maxlength="255" name="{{ $prefix }}[payment_method]" value="{{ $row['payment_method'] ?? '' }}" {{ $lockedRow ? 'readonly' : '' }}></td>
    @else
        <td>
            @if($lockedRow)<input type="hidden" name="{{ $prefix }}[tvde_week_id]" value="{{ $row['tvde_week_id'] }}">@endif
            <select aria-label="Semana TVDE" class="form-control week-select" name="{{ $prefix }}[tvde_week_id]" required {{ $lockedRow ? 'disabled' : '' }}>
                <option value="">Selecione a semana</option>
                @foreach($tvdeWeeks as $week)<option value="{{ $week->id }}" {{ ($row['tvde_week_id'] ?? '') == $week->id ? 'selected' : '' }}>{{ $week->start_date }} a {{ $week->end_date }}</option>@endforeach
            </select>
        </td>
    @endif
    <td>@if($lockedRow)<span class="text-muted">Registado</span>@else<button class="btn btn-default" type="button" data-remove-payment>Remover</button>@endif</td>
</tr>
