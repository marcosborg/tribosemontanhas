<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VehicleProfitabilityController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('vehicle_profitability_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.vehicleProfitabilities.index');
    }

}
