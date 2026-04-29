@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">Editar Caução</div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('admin.driver-deposits.update', $driverDeposit) }}">
                        @method('PUT')
                        @csrf
                        @include('admin.driverDeposits.partials.form', ['driverDeposit' => $driverDeposit, 'selectedWeeks' => old('tvde_weeks', $selectedWeeks)])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
