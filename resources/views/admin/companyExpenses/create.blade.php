@extends('layouts.admin')
@section('content')
<div class="content"><div class="row"><div class="col-lg-12"><div class="panel panel-default">
    <div class="panel-heading">{{ trans('global.create') }} {{ trans('cruds.companyExpense.title_singular') }}</div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.company-expenses.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.companyExpenses.form', ['companyExpense' => null])
        </form>
    </div>
</div></div></div></div>
@endsection
