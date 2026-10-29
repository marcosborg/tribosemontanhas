@php($destination = old('destination', isset($carTrack) && $carTrack->classification_status === 'company' ? 'company' : 'driver'))
<div class="form-group">
    <label for="destination">Destino</label>
    <select class="form-control" id="destination" name="destination" required>
        <option value="driver" {{ $destination === 'driver' ? 'selected' : '' }}>Motorista</option>
        <option value="company" {{ $destination === 'company' ? 'selected' : '' }}>Empresa</option>
    </select>
    <span class="help-block">O motorista é identificado automaticamente pela matrícula e pela utilização da viatura na data indicada.</span>
    @error('destination')<span class="help-block">{{ $message }}</span>@enderror
</div>
<div id="company-classification-fields">
    <div class="form-group">
        <label for="reason">Motivo</label>
        <select class="form-control" id="reason" name="reason">
            <option value="">Selecionar motivo</option>
            @foreach(['management_vehicle' => 'Viatura de gestão', 'personal_usage' => 'Utilização pessoal', 'annual_fee' => 'Anuidade'] as $key => $label)
                <option value="{{ $key }}" {{ old('reason', $carTrack->classification_reason ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('reason')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label for="company_id">Empresa</label>
        <select class="form-control" id="company_id" name="company_id">
            <option value="">Usar a empresa da viatura</option>
            @foreach($companies as $id => $name)
                <option value="{{ $id }}" {{ (string) old('company_id', $carTrack->company_id ?? (session('company_id') ?: ($companies->count() === 1 ? $companies->keys()->first() : ''))) === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>
        @error('company_id')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <p id="annual-fee-help" class="help-block">Na anuidade, indique o valor sem IVA. Será criada uma despesa da empresa com IVA de 23%, marcada como paga na data indicada.</p>
</div>
@section('scripts')
@parent
<script>
$(function () {
    function refreshClassification() {
        const company = $('#destination').val() === 'company';
        $('#company-classification-fields').toggle(company);
        $('#reason, #company_id').prop('disabled', !company);
        $('#reason').prop('required', company);
        $('#annual-fee-help').toggle(company && $('#reason').val() === 'annual_fee');
    }
    $('#destination, #reason').on('change', refreshClassification);
    refreshClassification();
});
</script>
@endsection
