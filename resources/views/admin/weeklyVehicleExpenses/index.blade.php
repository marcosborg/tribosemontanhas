@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Quilómetros semanais</strong></div>
        <div class="panel-body">
            <div class="row" style="margin-bottom: 15px;">
                <div class="col-md-5">
                    <label for="mileage_week_selector">Semana TVDE</label>
                    <select class="form-control select2" id="mileage_week_selector" style="width: 100%;">
                        @foreach($weeks as $week)
                            <option value="{{ $week->id }}" {{ (int) $selectedWeekId === (int) $week->id ? 'selected' : '' }}>
                                {{ $week->start_date }} — {{ $week->end_date }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @can('weekly_vehicle_expense_create')
                <div class="col-md-7" style="padding-top: 25px;">
                    <button class="btn btn-danger" data-toggle="modal" data-target="#teslaMileageModal">Importar Tesla</button>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#carTrackMileageModal">Importar CarTrack</button>
                </div>
                @endcan
            </div>

            @if($errors->any())
                <div class="alert alert-danger"><ul style="margin-bottom: 0;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @if(session('weeklyMileageImportReport'))
                @php($report = session('weeklyMileageImportReport'))
                <div class="alert alert-info">
                    <strong>Relatório:</strong>
                    {{ $report['created'] ?? 0 }} criadas,
                    {{ $report['updated'] ?? 0 }} atualizadas,
                    {{ $report['pending'] ?? 0 }} pendentes,
                    {{ $report['skipped'] ?? 0 }} ignoradas e
                    {{ count($report['failed'] ?? []) }} falhadas.
                    @if(!empty($report['failed']))
                    <div class="table-responsive" style="margin-top: 10px;"><table class="table table-condensed table-bordered"><thead><tr><th>Linha</th><th>Matrícula</th><th>Motivo</th></tr></thead><tbody>
                        @foreach($report['failed'] as $failure)<tr><td>{{ $failure['line'] }}</td><td>{{ $failure['license_plate'] }}</td><td>{{ $failure['reason'] }}</td></tr>@endforeach
                    </tbody></table></div>
                    @endif
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover ajaxTable datatable datatable-WeeklyVehicleExpense" style="width: 100%;">
                    <thead><tr>
                        <th width="10"></th><th>ID</th><th>Matrícula</th><th>Semana</th><th>Fonte</th>
                        <th>Conta-quilómetros</th><th>Km semanais</th><th>Motorista(s)</th><th>Km extra</th><th>Estado</th><th>Revisão</th><th></th>
                    </tr></thead>
                </table>
            </div>
        </div>
    </div>

    @can('weekly_vehicle_expense_create')
    @foreach(['tesla' => ['Tesla', 'admin.weekly-vehicle-expenses.importTesla', '.csv,.txt'], 'carTrack' => ['CarTrack', 'admin.weekly-vehicle-expenses.importCarTrack', '.xls,.xlsx']] as $key => $config)
    <div class="modal fade" id="{{ $key }}MileageModal" tabindex="-1" role="dialog">
        <div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route($config[1]) }}" enctype="multipart/form-data">@csrf
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Importar {{ $config[0] }}</h4></div>
            <div class="modal-body">
                <input type="hidden" name="tvde_week_id" class="mileage-week-hidden" value="{{ $selectedWeekId }}">
                <div class="form-group"><label>Semana</label><p class="form-control-static selected-week-label"></p></div>
                <div class="form-group"><label for="{{ $key }}_mileage_file">Ficheiro {{ $config[0] }}</label><input class="form-control" type="file" name="mileage_file" id="{{ $key }}_mileage_file" accept="{{ $config[2] }}" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Importar</button></div>
        </form></div></div>
    </div>
    @endforeach
    @endcan

    @can('weekly_vehicle_expense_edit')
    <div class="modal fade" id="mileageAllocationModal" tabindex="-1" role="dialog">
        <div class="modal-dialog"><div class="modal-content"><form method="POST" id="mileageAllocationForm">@csrf @method('PUT')
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Alocar quilómetros por motorista</h4></div>
            <div class="modal-body">
                <p>Total da viatura: <strong id="allocationTotalKm"></strong> km</p>
                <div id="allocationRows"></div>
                <button type="button" class="btn btn-xs btn-default" id="addAllocationRow">Adicionar motorista</button>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar alocação</button></div>
        </form></div></div>
    </div>
    @endcan
</div>
@endsection

@section('scripts')
@parent
<script>
$(function () {
  const drivers = @json($drivers);
  const allocationUrl = @json(route('admin.weekly-vehicle-expenses.allocations', ['weeklyVehicleExpense' => '__ID__']));
  const updateAllocationUrl = @json(route('admin.weekly-vehicle-expenses.updateAllocations', ['weeklyVehicleExpense' => '__ID__']));
  const $week = $('#mileage_week_selector');

  function syncWeek() {
    $('.mileage-week-hidden').val($week.val());
    $('.selected-week-label').text($week.find('option:selected').text().trim());
  }
  syncWeek();
  $week.on('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('tvde_week_id', this.value);
    window.location.href = url.toString();
  });
  $('.modal').on('show.bs.modal', syncWeek);

  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons);
  const table = $('.datatable-WeeklyVehicleExpense').DataTable({
    buttons: dtButtons,
    processing: true,
    serverSide: true,
    retrieve: true,
    ajax: { url: @json(route('admin.weekly-vehicle-expenses.index')), data: function (data) { data.tvde_week_id = $week.val(); } },
    columns: [
      { data: 'placeholder', name: 'placeholder' }, { data: 'id', name: 'id' },
      { data: 'vehicle_item_license_plate', name: 'vehicle_item.license_plate' },
      { data: 'tvde_week_start_date', name: 'tvde_week.start_date' }, { data: 'source', name: 'source' },
      { data: 'total_km', name: 'odometer_end' }, { data: 'weekly_km', name: 'distance_km' },
      { data: 'drivers', name: 'drivers', orderable: false, searchable: false },
      { data: 'extra_km', name: 'extra_km', orderable: false, searchable: false },
      { data: 'status', name: 'status' }, { data: 'review', name: 'review', orderable: false, searchable: false },
      { data: 'actions', name: @json(trans('global.actions')), orderable: false, searchable: false }
    ],
    order: [[1, 'desc']], pageLength: 100
  });

  function allocationRow(driverId, km) {
    let options = '<option value="">Selecionar motorista</option>';
    $.each(drivers, function (id, name) { options += '<option value="' + id + '"' + (String(id) === String(driverId) ? ' selected' : '') + '>' + $('<div>').text(name).html() + '</option>'; });
    return $('<div class="row allocation-row" style="margin-bottom:8px;">' +
      '<div class="col-xs-7"><select class="form-control" name="driver_ids[]" required>' + options + '</select></div>' +
      '<div class="col-xs-4"><input class="form-control" type="number" step="0.01" min="0.01" name="allocated_kms[]" value="' + (km || '') + '" placeholder="Km" required></div>' +
      '<div class="col-xs-1"><button type="button" class="btn btn-danger btn-sm remove-allocation">&times;</button></div></div>');
  }

  $(document).on('click', '.review-mileage', function () {
    const id = $(this).data('id');
    $.get(allocationUrl.replace('__ID__', id), function (response) {
      $('#allocationTotalKm').text(response.distance_km);
      $('#mileageAllocationForm').attr('action', updateAllocationUrl.replace('__ID__', id));
      const $rows = $('#allocationRows').empty();
      if (response.allocations.length) $.each(response.allocations, function (_, row) { $rows.append(allocationRow(row.driver_id, row.allocated_km)); });
      else $rows.append(allocationRow('', response.distance_km));
      $('#mileageAllocationModal').modal('show');
    });
  });
  $('#addAllocationRow').on('click', function () { $('#allocationRows').append(allocationRow('', '')); });
  $(document).on('click', '.remove-allocation', function () { $(this).closest('.allocation-row').remove(); });
});
</script>
@endsection
