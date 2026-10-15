@can('driver_deposit_edit')
    @if($movement->type === \App\Models\DriverDepositMovement::TYPE_REFUND)
        <a class="btn btn-xs btn-info" href="{{ route('admin.driver-deposit-movements.edit-refund', $movement) }}">Editar devolução</a>
    @endif
@endcan
