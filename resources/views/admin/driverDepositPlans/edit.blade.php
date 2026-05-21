@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Editar planeamento de caucao</div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.driver-deposit-plans.update', $driverDepositPlan) }}">
                @method('PUT')
                @csrf
                @include('admin.driverDepositPlans.partials.form')
            </form>
        </div>
    </div>
</div>
@endsection
