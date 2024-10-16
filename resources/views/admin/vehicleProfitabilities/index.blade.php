@extends('layouts.admin')
@section('content')
<div class="content">

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.vehicleProfitability.title') }}
                </div>
                <div class="panel-body">
                    <ul class="nav nav-tabs">
                        @foreach ($vehicle_items as $vehicle_item)
                        <li role="presentation" {{ $vehicle_item_id == $vehicle_item->id ? 'class="active"' : '' }}>
                            <a href="/admin/vehicle-profitabilities/set-vehicle-item-id/{{ $vehicle_item->id }}">{{ $vehicle_item->license_plate }} {{ $vehicle_item->driver ? '(' . $vehicle_item->driver->name . ')' : '' }}</a>
                        </li>
                        @endforeach
                    </ul>
                    <div class="row" style="margin-top: 20px; max-width: 50%;">
                        <form action="/admin/vehicle-profitabilities/set-interval" method="post">
                            @csrf
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Data inicial</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Data final</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-success form-control">Obter dados</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Mês</th>
                                        <th>Exercício Total</th>
                                        <th>Tesouraria</th>
                                        <th>IVA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Maio</td>
                                        <td>406.50€</td>
                                        <td>500.00€</td>
                                        <td>93.50€</td>
                                    </tr>
                                    <tr>
                                        <td>Maio</td>
                                        <td>406.50€</td>
                                        <td>500.00€</td>
                                        <td>93.50€</td>
                                    </tr>
                                    <tr>
                                        <td>Maio</td>
                                        <td>406.50€</td>
                                        <td>500.00€</td>
                                        <td>93.50€</td>
                                    </tr>
                                    <tr>
                                        <td>Maio</td>
                                        <td>406.50€</td>
                                        <td>500.00€</td>
                                        <td>93.50€</td>
                                    </tr>
                                    <tr>
                                        <td>Maio</td>
                                        <td>406.50€</td>
                                        <td>500.00€</td>
                                        <td>93.50€</td>
                                    </tr>
                                    <tr>
                                        <td>Maio</td>
                                        <td>406.50€</td>
                                        <td>500.00€</td>
                                        <td>93.50€</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection