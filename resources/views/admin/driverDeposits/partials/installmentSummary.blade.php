<h4>Pagamentos iniciais</h4>
<table class="table table-bordered"><thead><tr><th>Valor (€)</th><th>Data</th><th>Método de pagamento</th></tr></thead><tbody>
@foreach($plan->items()->where('kind', 'initial')->with('movements')->get() as $item)
    @php($receipt = $item->movements->firstWhere('type', 'payment'))
    <tr><td>{{ number_format($item->amount, 2, ',', '.') }}</td><td>{{ optional($receipt->payment_date)->format('d/m/Y') }}</td><td>{{ $receipt->payment_method }}</td></tr>
@endforeach
</tbody></table>
<h4>Planeamento semanal</h4>
<table class="table table-bordered"><thead><tr><th>Valor (€)</th><th>Semana TVDE</th><th>Estado</th></tr></thead><tbody>
@foreach($plan->items()->where('kind', 'weekly')->with('tvde_week')->orderBy('due_date')->get() as $item)
    <tr><td>{{ number_format($item->amount, 2, ',', '.') }}</td><td>{{ $item->tvde_week->start_date ?? '' }} a {{ $item->tvde_week->end_date ?? '' }}</td><td>{{ \App\Models\DriverDepositPlanItem::STATUS_SELECT[$item->status] ?? $item->status }}</td></tr>
@endforeach
</tbody></table>
<a class="btn btn-default" href="{{ route('admin.driver-deposit-plans.show', $plan) }}">Ver planeamento</a>
