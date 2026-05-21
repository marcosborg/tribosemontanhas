<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Driver;
use App\Services\DriverDepositPlanningService;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DriverDepositReconciliationController extends Controller
{
    public function index(Request $request, DriverDepositPlanningService $service)
    {
        abort_if(Gate::denies('driver_deposit_reconciliation_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $filters = $request->only(['company_id', 'driver_id', 'only_debt', 'only_positive_balance']);
        $rows = $service->reconciliationRows($filters);
        $cards = [
            'planned' => round($rows->sum('planned'), 2),
            'received' => round($rows->sum('received'), 2),
            'debt' => round($rows->sum('debt'), 2),
            'refunds' => round($rows->sum('refunds'), 2),
            'cash' => round($rows->sum('balance'), 2),
        ];

        return view('admin.driverDepositReconciliation.index', [
            'rows' => $rows,
            'cards' => $cards,
            'drivers' => Driver::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Driver $driver, DriverDepositPlanningService $service)
    {
        abort_if(Gate::denies('driver_deposit_reconciliation_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companyId = $request->input('company_id');
        $rows = $service->reconciliationRows([
            'driver_id' => $driver->id,
            'company_id' => $companyId,
        ]);
        $summary = $rows->first() ?: [
            'planned' => 0,
            'received' => 0,
            'debt' => 0,
            'refunds' => 0,
            'balance' => 0,
        ];
        $timeline = $service->timeline($driver->id, $companyId ? (int) $companyId : null);

        return view('admin.driverDepositReconciliation.show', compact('driver', 'summary', 'timeline', 'companyId'));
    }
}
