@php
    $mode = old('expense_mode', $companyExpense->expense_mode ?? App\Models\CompanyExpense::MODE_ACCOUNTING);
@endphp
<div class="form-group {{ $errors->has('expense_mode') ? 'has-error' : '' }}">
    <label class="required" for="expense_mode">Tipo de lançamento</label>
    <select class="form-control" name="expense_mode" id="expense_mode" required>
        <option value="accounting" {{ $mode === 'accounting' ? 'selected' : '' }}>Despesa contabilística</option>
        <option value="recurring" {{ $mode === 'recurring' ? 'selected' : '' }}>Custo semanal recorrente</option>
    </select>
</div>
<div class="form-group {{ $errors->has('company_id') ? 'has-error' : '' }}">
    <label class="required" for="company_id">{{ trans('cruds.companyExpense.fields.company') }}</label>
    <select class="form-control select2" name="company_id" id="company_id" required>
        @foreach($companies as $id => $entry)
            <option value="{{ $id }}" {{ (string) old('company_id', $companyExpense->company_id ?? session('company_id')) === (string) $id ? 'selected' : '' }}>{{ $entry }}</option>
        @endforeach
    </select>
    @if($errors->has('company_id'))<span class="help-block">{{ $errors->first('company_id') }}</span>@endif
</div>

<div id="accounting_fields">
    <div class="form-group {{ $errors->has('expense_type') ? 'has-error' : '' }}">
        <label class="required">Tipo de despesa</label>
        @foreach(App\Models\CompanyExpense::EXPENSE_TYPE_RADIO as $key => $label)
            <div><input type="radio" id="expense_type_{{ $loop->index }}" name="expense_type" value="{{ $key }}" {{ old('expense_type', $companyExpense->expense_type ?? array_key_first(App\Models\CompanyExpense::EXPENSE_TYPE_RADIO)) === (string) $key ? 'checked' : '' }}>
                <label for="expense_type_{{ $loop->index }}" style="font-weight:400">{{ $label }}</label></div>
        @endforeach
        @if($errors->has('expense_type'))<span class="help-block">{{ $errors->first('expense_type') }}</span>@endif
    </div>
    <div class="form-group {{ $errors->has('date') ? 'has-error' : '' }}">
        <label class="required" for="date">Data</label>
        <input class="form-control date" type="text" name="date" id="date" value="{{ old('date', $companyExpense->date ?? '') }}">
        @if($errors->has('date'))<span class="help-block">{{ $errors->first('date') }}</span>@endif
    </div>
    <div class="form-group"><label for="description">Descrição</label><textarea class="form-control ckeditor" name="description" id="description">{!! old('description', $companyExpense->description ?? '') !!}</textarea></div>
    <div class="form-group {{ $errors->has('documents') ? 'has-error' : '' }}">
        <label for="documents">Fatura / documentos</label>
        <input class="form-control" type="file" name="documents[]" id="documents" multiple>
        @if($companyExpense && $companyExpense->files->isNotEmpty())
            <ul style="margin-top:10px">@foreach($companyExpense->files as $file)<li><a href="{{ $file->getUrl() }}" target="_blank">{{ $file->file_name }}</a></li>@endforeach</ul>
        @endif
    </div>
    <div class="form-group"><label for="payment_reference">Referência de pagamento</label><input class="form-control" name="payment_reference" id="payment_reference" value="{{ old('payment_reference', $companyExpense->payment_reference ?? '') }}"></div>
    <div class="form-group"><label for="pay_to">Pagar a</label><input class="form-control" name="pay_to" id="pay_to" value="{{ old('pay_to', $companyExpense->pay_to ?? '') }}"></div>
    <div class="form-group"><input type="hidden" name="is_paid" value="0"><input type="checkbox" name="is_paid" id="is_paid" value="1" {{ old('is_paid', $companyExpense->is_paid ?? false) ? 'checked' : '' }}> <label for="is_paid" style="font-weight:400">Já pago</label></div>
    <div class="row">
        <div class="col-md-4 form-group {{ $errors->has('value') ? 'has-error' : '' }}"><label class="required" for="value">Valor</label><input class="form-control" type="number" step="0.01" min="0" name="value" id="value" value="{{ old('value', $companyExpense->value ?? 0) }}"></div>
        <div class="col-md-4 form-group"><label for="invoice_value">Valor final</label><input class="form-control" type="number" step="0.01" min="0" name="invoice_value" id="invoice_value" value="{{ old('invoice_value', $companyExpense->invoice_value ?? '') }}"></div>
        <div class="col-md-4 form-group {{ $errors->has('vat') ? 'has-error' : '' }}"><label class="required" for="vat">IVA</label><input class="form-control" type="number" step="0.01" min="0" name="vat" id="vat" value="{{ old('vat', $companyExpense->vat ?? 23) }}"></div>
    </div>
</div>

<div id="recurring_fields">
    <div class="form-group"><label class="required" for="name">Nome</label><input class="form-control" name="name" id="name" value="{{ old('name', $companyExpense->name ?? '') }}"></div>
    <div class="form-group"><label class="required" for="weekly_value">Valor semanal</label><input class="form-control" type="number" step="0.01" min="0" name="weekly_value" id="weekly_value" value="{{ old('weekly_value', $companyExpense->weekly_value ?? 0) }}"></div>
    <div class="row"><div class="col-md-4 form-group"><label class="required" for="start_date">Data inicial</label><input class="form-control date" name="start_date" id="start_date" value="{{ old('start_date', $companyExpense->start_date ?? '') }}"></div>
    <div class="col-md-4 form-group"><label class="required" for="end_date">Data final</label><input class="form-control date" name="end_date" id="end_date" value="{{ old('end_date', $companyExpense->end_date ?? '') }}"></div>
    <div class="col-md-4 form-group"><label class="required" for="qty">Quantidade</label><input class="form-control" type="number" min="0" name="qty" id="qty" value="{{ old('qty', $companyExpense->qty ?? 1) }}"></div></div>
</div>
<div class="form-group"><button class="btn btn-danger" type="submit">{{ trans('global.save') }}</button></div>

@section('scripts')
@parent
<script>
$(function () {
    function toggleExpenseMode() {
        var accounting = $('#expense_mode').val() === 'accounting';
        $('#accounting_fields').toggle(accounting).find('input, textarea, select').prop('disabled', !accounting);
        $('#recurring_fields').toggle(!accounting).find('input, textarea, select').prop('disabled', accounting);
    }
    $('#expense_mode').on('change', toggleExpenseMode);
    toggleExpenseMode();
});
</script>
@endsection
