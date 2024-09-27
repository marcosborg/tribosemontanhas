<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\Traits\Reports;
use App\Models\Receipt;

class DriversPaymentsController extends Controller
{

    use Reports;

    public function index()
    {
        abort_if(Gate::denies('drivers_payment_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $receipts = Receipt::where([
            'verified' => true,
            'paid' => false
        ])->get()->load('driver');

        return view('admin.driversPayments.index')->with([
            'receipts' => $receipts,
        ]);
    }
}
