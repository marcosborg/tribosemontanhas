@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">Criar Caução</div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('admin.driver-deposits.store') }}">
                        @csrf
                        @include('admin.driverDeposits.partials.form', ['driverDeposit' => null, 'selectedWeeks' => old('tvde_weeks', [])])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
