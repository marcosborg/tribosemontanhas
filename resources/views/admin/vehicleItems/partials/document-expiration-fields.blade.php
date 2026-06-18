@php($documentExpirationFields = \App\Services\PendingItemsService::DOCUMENT_FIELDS)

<div class="panel panel-default">
    <div class="panel-heading">
        Datas de expiracao de documentos
    </div>
    <div class="panel-body">
        <div class="row">
            @foreach($documentExpirationFields as $field => $label)
                @php($value = old($field, isset($vehicleItem) && $vehicleItem->{$field} ? $vehicleItem->{$field}->format('Y-m-d') : ''))
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has($field) ? 'has-error' : '' }}">
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input class="form-control" type="date" name="{{ $field }}" id="{{ $field }}" value="{{ $value }}">
                        @if($errors->has($field))
                            <span class="help-block" role="alert">{{ $errors->first($field) }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
