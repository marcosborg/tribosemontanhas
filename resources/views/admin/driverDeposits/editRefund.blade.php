@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Editar devolução de caução — {{ $movement->deposit->driver->name ?? '' }}</div>
        <div class="panel-body">
            <p>Corrija o valor, a origem, a semana ou a descrição desta devolução. Para dividir uma devolução entre as duas origens, guarde aqui uma parte e registe a restante na caução.</p>
            <form method="POST" action="{{ route('admin.driver-deposit-movements.update-refund', $movement) }}">
                @csrf
                @method('PUT')
                @include('admin.driverDeposits.partials.movementForm', [
                    'isRefund' => true, 'defaultAmount' => $movement->amount,
                    'defaultWeekId' => $movement->tvde_week_id, 'defaultDescription' => $movement->description,
                    'defaultRefundSource' => $movement->refund_source, 'button' => 'Guardar devolução',
                ])
                <a class="btn btn-default" href="{{ route('admin.driver-deposits.show', $movement->driver_deposit_id) }}">Cancelar</a>
            </form>
        </div>
    </div>
</div>
@endsection
