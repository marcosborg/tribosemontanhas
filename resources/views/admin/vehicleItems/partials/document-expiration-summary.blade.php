@php($documentExpirationFields = \App\Services\PendingItemsService::DOCUMENT_FIELDS)

@foreach($documentExpirationFields as $field => $label)
    @if($vehicleItem->{$field})
        <div>
            <strong>{{ $label }}:</strong> {{ $vehicleItem->{$field}->format('Y-m-d') }}
        </div>
    @endif
@endforeach
