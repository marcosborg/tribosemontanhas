@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Drivers inativos</div>
        <div class="panel-body">
            <p class="text-muted">Drivers com estado Inativo na empresa selecionada. O fim da última utilização é uma referência para a inativação, não um registo da alteração de estado. Não inclui períodos de manutenção, sinistro ou sem utilização.</p>
            <form id="driver-filters" style="margin-bottom: 20px;">
                <div class="row">
                    @foreach([0 => 'Nome', 1 => 'Email', 2 => 'Telefone', 4 => 'Última viatura'] as $column => $label)
                        <div class="col-sm-3 form-group">
                            <label for="driver-filter-{{ $column }}">{{ $label }}</label>
                            <input id="driver-filter-{{ $column }}" class="form-control" type="search" data-column="{{ $column }}" placeholder="Filtrar {{ mb_strtolower($label) }}">
                        </div>
                    @endforeach
                </div>
                <div class="row">
                    @foreach([3 => 'Entrada na empresa', 5 => 'Início da primeira alocação', 6 => 'Fim da última utilização'] as $column => $label)
                        <fieldset class="col-sm-4 form-group">
                            <legend style="font-size:14px; border:0; margin-bottom:5px; font-weight:700;">{{ $label }}</legend>
                            <div class="row">
                                <div class="col-xs-6">
                                    <label for="date-from-{{ $column }}">De</label>
                                    <input id="date-from-{{ $column }}" type="date" class="form-control" aria-label="{{ $label }}: de">
                                </div>
                                <div class="col-xs-6">
                                    <label for="date-to-{{ $column }}">Até</label>
                                    <input id="date-to-{{ $column }}" type="date" class="form-control" aria-label="{{ $label }}: até">
                                </div>
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                <div class="row">
                    <div class="col-sm-4 form-group">
                        <label for="allocation-status">Histórico de alocação</label>
                        <select id="allocation-status" class="form-control">
                            <option value="">Todos</option>
                            <option value="closed">Com fim registado</option>
                            <option value="open">Sem fim registado</option>
                            <option value="none">Sem alocação registada</option>
                        </select>
                    </div>
                    <div class="col-sm-8" style="padding-top:25px;">
                        <button type="reset" class="btn btn-default">Limpar filtros</button>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="inactive-drivers" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Entrada na empresa</th>
                            <th>Última viatura</th>
                            <th>Início da primeira alocação</th>
                            <th>Fim da última utilização</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($drivers as $driver)
                            @php($usage = $driver->latestVehicleAllocation)
                            @php($firstUsage = $driver->firstVehicleAllocation)
                            <tr data-date-3="{{ $driver->getRawOriginal('start_date') }}" data-date-5="{{ $firstUsage->start_date ?? '' }}" data-date-6="{{ $usage->end_date ?? '' }}">
                                <td>{{ $driver->name }}</td>
                                <td>{{ $driver->email ?: '—' }}</td>
                                <td>{{ $driver->phone ?: '—' }}</td>
                                <td data-order="{{ $driver->getRawOriginal('start_date') }}">{{ $driver->getRawOriginal('start_date') ? \Carbon\Carbon::parse($driver->getRawOriginal('start_date'))->format('d/m/Y') : '—' }}</td>
                                <td>{{ $usage ? ($usage->vehicle_item->license_plate ?? 'Viatura indisponível') : 'Sem alocação registada' }}</td>
                                <td data-order="{{ $firstUsage->start_date ?? '' }}">{{ $firstUsage ? \Carbon\Carbon::parse($firstUsage->start_date)->format('d/m/Y H:i:s') : '—' }}</td>
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
    const dateColumns = [3, 5, 6];
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'inactive-drivers') return true;
        const row = settings.aoData[dataIndex].nTr;
        const dateValue = column => (row.getAttribute('data-date-' + column) || '').slice(0, 10);
        const status = $('#allocation-status').val();
        if (status === 'none' && dateValue(5)) return false;
        if (status === 'open' && (!dateValue(5) || dateValue(6))) return false;
        if (status === 'closed' && !dateValue(6)) return false;
        return dateColumns.every(column => {
            const from = $('#date-from-' + column).val();
            const to = $('#date-to-' + column).val();
            const value = dateValue(column);
            return (!from && !to) || (value && (!from || value >= from) && (!to || value <= to));
        });
    });
    const table = $('#inactive-drivers').DataTable({
        buttons: $.fn.dataTable.defaults.buttons.filter(button => !['selectAll', 'selectNone'].includes(button.extend)),
        order: [[0, 'asc']],
        pageLength: 25,
        select: false,
        columnDefs: [],
        language: {emptyTable: 'Não existem drivers inativos na empresa selecionada.'}
    });
    $('#driver-filters').on('submit', function (event) { event.preventDefault(); });
    $('#driver-filters [data-column]').on('input', function () {
        table.column(Number(this.dataset.column)).search(this.value).draw();
    });
    $('#driver-filters input[type=date], #allocation-status').on('change', function () { table.draw(); });
    $('#driver-filters').on('reset', function (event) {
        event.preventDefault();
        $(this).find('input, select').val('');
        table.search('').columns().search('').draw();
    });
});
</script>
@endsection
