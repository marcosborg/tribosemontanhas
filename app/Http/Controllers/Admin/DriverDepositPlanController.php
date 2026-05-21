<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverDepositPlan;
use App\Models\DriverDepositPlanItem;
use App\Models\TvdeWeek;
use App\Services\DriverDepositPlanningService;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DriverDepositPlanController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('driver_deposit_plan_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $filters = $request->only(['driver_id', 'company_id', 'status', 'tvde_week_id', 'date_from', 'date_to']);

        $plans = DriverDepositPlan::with(['driver', 'company', 'start_week', 'items'])
            ->when($filters['driver_id'] ?? null, fn ($query, $driverId) => $query->where('driver_id', $driverId))
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['tvde_week_id'] ?? null, fn ($query, $weekId) => $query->whereHas('items', fn ($item) => $item->where('tvde_week_id', $weekId)))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereHas('items', fn ($item) => $item->where('due_date', '>=', $date)))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereHas('items', fn ($item) => $item->where('due_date', '<=', $date)))
            ->orderByDesc('id')
            ->get();

        if ($request->input('export') === 'csv') {
            return $this->exportPlans($plans);
        }

        return view('admin.driverDepositPlans.index', array_merge($this->formData(), compact('plans', 'filters')));
    }

    public function create()
    {
        abort_if(Gate::denies('driver_deposit_plan_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.driverDepositPlans.create', $this->formData());
    }

    public function store(Request $request, DriverDepositPlanningService $service)
    {
        abort_if(Gate::denies('driver_deposit_plan_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $plan = $service->createPlan($this->validatedPlanData($request));

        return redirect()->route('admin.driver-deposit-plans.show', $plan)->with('message', 'Plano de caucao criado com sucesso.');
    }

    public function show(DriverDepositPlan $driverDepositPlan)
    {
        abort_if(Gate::denies('driver_deposit_plan_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $driverDepositPlan->load(['driver', 'company', 'start_week', 'items.tvde_week']);
        $items = $driverDepositPlan->items->sortBy('due_date');
        $totalPlanned = round((float) $items->where('status', '!=', DriverDepositPlanItem::STATUS_CANCELLED)->sum('amount'), 2);
        $totalPaid = round((float) $items->sum('paid_amount'), 2);
        $futureTotal = round((float) $items->where('status', DriverDepositPlanItem::STATUS_PENDING)->sum('amount'), 2);
        $overdueTotal = round((float) $items->where('status', DriverDepositPlanItem::STATUS_OVERDUE)->sum('amount'), 2);

        return view('admin.driverDepositPlans.show', compact('driverDepositPlan', 'items', 'totalPlanned', 'totalPaid', 'futureTotal', 'overdueTotal'));
    }

    public function edit(DriverDepositPlan $driverDepositPlan)
    {
        abort_if(Gate::denies('driver_deposit_plan_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.driverDepositPlans.edit', array_merge($this->formData(), compact('driverDepositPlan')));
    }

    public function update(Request $request, DriverDepositPlan $driverDepositPlan, DriverDepositPlanningService $service)
    {
        abort_if(Gate::denies('driver_deposit_plan_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $service->updatePlan($driverDepositPlan, $this->validatedPlanData($request));

        return redirect()->route('admin.driver-deposit-plans.show', $driverDepositPlan)->with('message', 'Plano de caucao atualizado com sucesso.');
    }

    public function pause(DriverDepositPlan $driverDepositPlan)
    {
        abort_if(Gate::denies('driver_deposit_plan_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $driverDepositPlan->update(['status' => DriverDepositPlan::STATUS_PAUSED]);

        return back()->with('message', 'Plano pausado com sucesso.');
    }

    public function reactivate(DriverDepositPlan $driverDepositPlan)
    {
        abort_if(Gate::denies('driver_deposit_plan_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $driverDepositPlan->update(['status' => DriverDepositPlan::STATUS_ACTIVE]);

        return back()->with('message', 'Plano reativado com sucesso.');
    }

    public function recalculate(DriverDepositPlan $driverDepositPlan, DriverDepositPlanningService $service)
    {
        abort_if(Gate::denies('driver_deposit_plan_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $service->generateItems($driverDepositPlan);

        return back()->with('message', 'Parcelas recalculadas com sucesso.');
    }

    public function destroy(DriverDepositPlan $driverDepositPlan)
    {
        abort_if(Gate::denies('driver_deposit_plan_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $hasPaidItems = $driverDepositPlan->items()
            ->where(function ($query) {
                $query->where('paid_amount', '>', 0)
                    ->orWhere('status', DriverDepositPlanItem::STATUS_PAID);
            })
            ->exists();

        if ($hasPaidItems) {
            return back()->withErrors('Nao e possivel apagar um plano com parcelas ja pagas.');
        }

        $driverDepositPlan->delete();

        return redirect()->route('admin.driver-deposit-plans.index')->with('message', 'Plano apagado com sucesso.');
    }

    private function formData(): array
    {
        return [
            'drivers' => Driver::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'tvdeWeeks' => TvdeWeek::orderByDesc('start_date')->get(),
            'statuses' => DriverDepositPlan::STATUS_SELECT,
        ];
    }

    private function validatedPlanData(Request $request): array
    {
        return $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'initial_amount' => ['nullable', 'numeric', 'min:0'],
            'weekly_amount' => ['required', 'numeric', 'min:0'],
            'total_weeks' => ['required', 'integer', 'min:0'],
            'start_week_id' => ['required', 'integer', 'exists:tvde_weeks,id'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function exportPlans($plans)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="planeamento-caucoes.csv"',
        ];

        return response()->streamDownload(function () use ($plans) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Motorista', 'Empresa', 'Estado', 'Total previsto', 'Total pago', 'Parcelas']);

            foreach ($plans as $plan) {
                fputcsv($out, [
                    $plan->driver->name ?? '',
                    $plan->company->name ?? '',
                    DriverDepositPlan::STATUS_SELECT[$plan->status] ?? $plan->status,
                    $plan->items->sum('amount'),
                    $plan->items->sum('paid_amount'),
                    $plan->items->count(),
                ]);
            }

            fclose($out);
        }, 'planeamento-caucoes.csv', $headers);
    }
}
