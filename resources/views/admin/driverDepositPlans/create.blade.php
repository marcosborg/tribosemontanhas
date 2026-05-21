@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="panel panel-default">
        <div class="panel-heading">Criar planeamento de caucao</div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.driver-deposit-plans.store') }}">
                @csrf
                @include('admin.driverDepositPlans.partials.form', ['driverDepositPlan' => null])
            </form>
        </div>
    </div>
</div>
@endsection
