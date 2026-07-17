@extends('layouts.admin')
@section('content')
<div class="content"><div class="row"><div class="col-lg-12"><div class="panel panel-default">
    <div class="panel-heading">{{ trans('global.edit') }} {{ trans('cruds.companyExpense.title_singular') }}</div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.company-expenses.update', [$companyExpense->id]) }}" enctype="multipart/form-data">
            @method('PUT') @csrf
            @include('admin.companyExpenses.form', ['companyExpense' => $companyExpense])
        </form>
    </div>
</div></div></div></div>
@endsection
