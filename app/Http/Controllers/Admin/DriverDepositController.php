<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CurrentAccount;
use App\Models\Driver;
use App\Models\DriverDeposit;
use App\Models\DriverDepositMovement;
use App\Models\TvdeMonth;
use App\Models\TvdeWeek;
use App\Services\DriverDepositService;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DriverDepositController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('driver_deposit_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $monthId = $request->input('tvde_month_id', session()->get('tvde_month_id'));
        $driverId = $request->input('driver_id');
        $companyId = $request->input('company_id', session()->get('company_id') !== '0' ? session()->get('company_id') : null);
        $type = $request->input('type');

        $movements = DriverDepositMovement::with(['deposit', 'driver.company', 'company', 'tvde_week.tvde_month'])
            ->when($monthId, function ($query) use ($monthId) {
                $query->whereHas('tvde_week', fn ($week) => $week->where('tvde_month_id', $monthId));
            })
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->leftJoin('tvde_weeks', 'driver_deposit_movements.tvde_week_id', '=', 'tvde_weeks.id')
            ->orderByDesc('tvde_weeks.start_date')
            ->orderByDesc('driver_deposit_movements.id')
            ->select('driver_deposit_movements.*')
            ->get();

        $months = TvdeMonth::orderByDesc('id')->get();
        $drivers = Driver::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();
        $types = DriverDepositMovement::TYPE_SELECT;

        return view('admin.driverDeposits.index', compact('movements', 'months', 'drivers', 'companies', 'types', 'monthId', 'driverId', 'companyId', 'type'));
    }

    public function create()
    {
        abort_if(Gate::denies('driver_deposit_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.driverDeposits.create', $this->formData());
    }

    public function store(Request $request, DriverDepositService $service)
    {
        abort_if(Gate::denies('driver_deposit_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $this->validatedDepositData($request);

        DB::transaction(function () use ($data, $service) {
            $deposit = DriverDeposit::create($data);
            $service->syncPlannedMovements($deposit, $data['tvde_weeks']);
        });

        return redirect()->route('admin.driver-deposits.index')->with('message', 'Caucao criada com sucesso.');
    }

    public function show(DriverDeposit $driverDeposit, DriverDepositService $service)
    {
        abort_if(Gate::denies('driver_deposit_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $driverDeposit->load(['driver.company', 'company', 'movements.tvde_week']);

        $movements = $driverDeposit->movements()
            ->with('tvde_week')
            ->leftJoin('tvde_weeks', 'driver_deposit_movements.tvde_week_id', '=', 'tvde_weeks.id')
            ->orderByRaw('COALESCE(tvde_weeks.start_date, driver_deposit_movements.created_at)')
            ->orderBy('driver_deposit_movements.id')
            ->select('driver_deposit_movements.*')
            ->get();

        $suggestedRefundWeekId = $service->suggestedRefundWeekId($driverDeposit);
        $availableBalance = $service->availableBalance($driverDeposit, $suggestedRefundWeekId);
        $tvdeWeeks = TvdeWeek::orderByDesc('start_date')->get();

        return view('admin.driverDeposits.show', compact('driverDeposit', 'movements', 'availableBalance', 'suggestedRefundWeekId', 'tvdeWeeks'));
    }

    public function edit(DriverDeposit $driverDeposit)
    {
        abort_if(Gate::denies('driver_deposit_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $driverDeposit->load('movements');
        $selectedWeeks = $driverDeposit->movements()
            ->whereIn('type', [DriverDepositMovement::TYPE_INITIAL_CHARGE, DriverDepositMovement::TYPE_WEEKLY_CHARGE])
            ->pluck('tvde_week_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('admin.driverDeposits.edit', array_merge(
            $this->formData(),
            compact('driverDeposit', 'selectedWeeks')
        ));
    }

    public function update(Request $request, DriverDeposit $driverDeposit, DriverDepositService $service)
    {
        abort_if(Gate::denies('driver_deposit_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $this->validatedDepositData($request);

        DB::transaction(function () use ($driverDeposit, $data, $service) {
            $driverDeposit->update($data);
            $service->syncPlannedMovements($driverDeposit, $data['tvde_weeks']);
        });

        return redirect()->route('admin.driver-deposits.show', $driverDeposit)->with('message', 'Caucao atualizada com sucesso.');
    }

    public function destroy(DriverDeposit $driverDeposit)
    {
        abort_if(Gate::denies('driver_deposit_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $hasValidatedWeek = CurrentAccount::where('driver_id', $driverDeposit->driver_id)
            ->whereIn('tvde_week_id', $driverDeposit->movements()->pluck('tvde_week_id')->filter())
            ->exists();

        if ($hasValidatedWeek) {
            return back()->withErrors('Nao e possivel apagar uma caucao com movimentos em semanas ja validadas.');
        }

        $driverDeposit->delete();

        return redirect()->route('admin.driver-deposits.index')->with('message', 'Caucao apagada com sucesso.');
    }

    public function destroyMovement(DriverDepositMovement $movement, DriverDepositService $service)
    {
        abort_if(Gate::denies('driver_deposit_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($this->movementHasValidatedWeek($movement)) {
            return back()->withErrors('Nao e possivel apagar um movimento de uma semana ja validada.');
        }

        $deposit = $movement->deposit;

        DB::transaction(function () use ($movement, $deposit, $service) {
            $movement->delete();

            if ($deposit) {
                $service->recalculateBalances($deposit);
            }
        });

        return back()->with('message', 'Movimento apagado com sucesso.');
    }

    public function massDestroyMovements(Request $request, DriverDepositService $service)
    {
        abort_if(Gate::denies('driver_deposit_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:driver_deposit_movements,id'],
        ]);

        $movements = DriverDepositMovement::with('deposit')
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($movements as $movement) {
            if ($this->movementHasValidatedWeek($movement)) {
                return response('Nao e possivel apagar movimentos de semanas ja validadas.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        DB::transaction(function () use ($movements, $service) {
            $deposits = $movements->pluck('deposit')->filter()->unique('id');

            foreach ($movements as $movement) {
                $movement->delete();
            }

            foreach ($deposits as $deposit) {
                $service->recalculateBalances($deposit);
            }
        });

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function internalDebit(Request $request, DriverDeposit $driverDeposit, DriverDepositService $service)
    {
        abort_if(Gate::denies('driver_deposit_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'tvde_week_id' => ['required', 'integer', 'exists:tvde_weeks,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
        ]);

        $service->createInternalDebit($driverDeposit, (int) $data['tvde_week_id'], (float) $data['amount'], $data['description'] ?? null);

        return back()->with('message', 'Abatimento registado com sucesso.');
    }

    public function refund(Request $request, DriverDeposit $driverDeposit, DriverDepositService $service)
    {
        abort_if(Gate::denies('driver_deposit_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'tvde_week_id' => ['required', 'integer', 'exists:tvde_weeks,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
        ]);

        $service->createRefund($driverDeposit, (int) $data['tvde_week_id'], (float) $data['amount'], $data['description'] ?? null);

        return back()->with('message', 'Devolucao registada com sucesso.');
    }

    private function formData(): array
    {
        return [
            'drivers' => Driver::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'tvdeWeeks' => TvdeWeek::orderByDesc('start_date')->get(),
            'statuses' => DriverDeposit::STATUS_SELECT,
        ];
    }

    private function validatedDepositData(Request $request): array
    {
        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'initial_payment' => ['nullable', 'numeric', 'min:0'],
            'weekly_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'tvde_weeks' => ['required', 'array'],
            'tvde_weeks.*' => ['integer', 'exists:tvde_weeks,id'],
        ]);

        $data['initial_payment'] = $data['initial_payment'] ?? 0;

        return $data;
    }

    private function movementHasValidatedWeek(DriverDepositMovement $movement): bool
    {
        if (!$movement->tvde_week_id) {
            return false;
        }

        return CurrentAccount::where('driver_id', $movement->driver_id)
            ->where('tvde_week_id', $movement->tvde_week_id)
            ->exists();
    }
}
