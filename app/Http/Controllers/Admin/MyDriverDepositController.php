<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverDeposit;
use App\Models\DriverDepositMovement;
use App\Services\DriverDepositService;
use Gate;
use Symfony\Component\HttpFoundation\Response;

class MyDriverDepositController extends Controller
{
    public function index(DriverDepositService $service)
    {
        abort_if(
            Gate::denies('my_receipt_access') && Gate::denies('my_document_access'),
            Response::HTTP_FORBIDDEN,
            '403 Forbidden'
        );

        $driver = Driver::where('user_id', auth()->id())->first();
        abort_if(!$driver, Response::HTTP_NOT_FOUND, 'Motorista nao encontrado.');

        $deposits = DriverDeposit::with(['company', 'movements.tvde_week'])
            ->where('driver_id', $driver->id)
            ->orderByDesc('id')
            ->get();

        $depositBalances = $deposits
            ->mapWithKeys(fn (DriverDeposit $deposit) => [$deposit->id => $service->availableBalance($deposit)])
            ->all();

        $currentBalance = round(array_sum($depositBalances), 2);

        $movements = DriverDepositMovement::with(['deposit.company', 'tvde_week'])
            ->where('driver_id', $driver->id)
            ->leftJoin('tvde_weeks', 'driver_deposit_movements.tvde_week_id', '=', 'tvde_weeks.id')
            ->orderByRaw('COALESCE(tvde_weeks.start_date, driver_deposit_movements.created_at) DESC')
            ->orderByDesc('driver_deposit_movements.id')
            ->select('driver_deposit_movements.*')
            ->get();

        return view('admin.myDriverDeposit.index', compact('driver', 'deposits', 'depositBalances', 'currentBalance', 'movements'));
    }
}
