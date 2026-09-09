@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Drivers inativos</div>
        <div class="panel-body">
            <p class="text-muted">Drivers com estado Inativo na empresa selecionada. O fim da última utilização é uma referência para a inativação, não um registo da alteração de estado. Não inclui períodos de manutenção, sinistro ou sem utilização.</p>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="inactive-drivers" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Entrada na empresa</th>
                            <th>Última viatura</th>
                            <th>Início da última alocação</th>
                            <th>Fim da última utilização</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($drivers as $driver)
                            @php($usage = $driver->latestVehicleAllocation)
                            <tr>
                                <td>{{ $driver->name }}</td>
                                <td>{{ $driver->email ?: '—' }}</td>
                                <td>{{ $driver->phone ?: '—' }}</td>
                                <td data-order="{{ $driver->getRawOriginal('start_date') }}">{{ $driver->getRawOriginal('start_date') ? \Carbon\Carbon::parse($driver->getRawOriginal('start_date'))->format('d/m/Y') : '—' }}</td>
                                <td>{{ $usage ? ($usage->vehicle_item->license_plate ?? 'Viatura indisponível') : 'Sem alocação registada' }}</td>
                                <td data-order="{{ $usage->start_date ?? '' }}">{{ $usage ? \Carbon\Carbon::parse($usage->start_date)->format('d/m/Y H:i:s') : '—' }}</td>
                                <td data-order="{{ $usage->end_date ?? '' }}">{{ $usage ? ($usage->end_date ? \Carbon\Carbon::parse($usage->end_date)->format('d/m/Y H:i:s') : 'Sem fim registado') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script>
$(function () {
    $('#inactive-drivers').DataTable({
        buttons: $.fn.dataTable.defaults.buttons.filter(button => !['selectAll', 'selectNone'].includes(button.extend)),
        order: [[0, 'asc']],
        pageLength: 25,
        select: false,
        columnDefs: [],
        language: {emptyTable: 'Não existem drivers inativos na empresa selecionada.'}
    });
});
</script>
@endsection
