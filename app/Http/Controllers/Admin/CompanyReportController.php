<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Gate;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\Traits\Reports;
use Illuminate\Http\Request;
use App\Models\CurrentAccount;
use App\Models\DriversBalance;
use App\Models\Driver;
use App\Models\Reimbursement;

class CompanyReportController extends Controller
{

    use Reports;

    public function index()
    {
        abort_if(Gate::denies('company_report_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $filter = $this->filter();
        $company_id = $filter['company_id'];
        $tvde_week_id = $filter['tvde_week_id'];
        $tvde_years = $filter['tvde_years'];
        $tvde_year_id = $filter['tvde_year_id'];
        $tvde_months = $filter['tvde_months'];
        $tvde_month_id = $filter['tvde_month_id'];
        $tvde_weeks = $filter['tvde_weeks'];

        $results = $this->getWeekReport($company_id, $tvde_week_id);

        return view('admin.companyReports.index')->with([
            'company_id' => $company_id,
            'tvde_years' => $tvde_years,
            'tvde_year_id' => $tvde_year_id,
            'tvde_months' => $tvde_months,
            'tvde_month_id' => $tvde_month_id,
            'tvde_weeks' => $tvde_weeks,
            'tvde_week_id' => $tvde_week_id,
            'drivers' => $results['drivers'],
            'totals' => $results['totals']
        ]);
    }

    public function validateData(Request $request)
    {
        $filter = $this->filter();
        $company_id = $filter['company_id'];
        $tvde_week_id = $filter['tvde_week_id'];
        $results = $this->getWeekReport($company_id, $tvde_week_id);
        $driversById = collect($results['drivers'] ?? [])->keyBy('id');

        foreach ($request->data as $data) {

            // 🔹 Função inline para normalizar valores vindos do front
            $normalize = function ($value): float {
                if (is_numeric($value)) {
                    return (float) $value; // já é número limpo
                }
                $v = str_replace(' ', '', (string) $value);   // remove espaços normais
                $v = str_replace("\xc2\xa0", '', $v);         // remove NBSP (utf-8)
                $v = str_replace('.', '', $v);                // tira separador de milhar
                $v = str_replace(',', '.', $v);               // vírgula → ponto decimal
                return (float) $v;
            };

            // 🔹 Normaliza o total do motorista
            $total = $normalize($data['driver']['total']);

            $earnings = $data['driver']['earnings'] ?? [];
            if (!is_array($earnings)) {
                $earnings = (array) $earnings;
            }

            $serverDriver = $driversById->get($data['driver']['id']);
            $detailsPayload = $this->buildFuelDetailsPayload($serverDriver);
            if (!empty($detailsPayload)) {
                $earnings = array_merge($earnings, $detailsPayload);
            }

            // Registo/atualizacao da conta corrente
            CurrentAccount::updateOrCreate(
                [
                    'tvde_week_id' => $data['tvde_week_id'],
                    'driver_id'    => $data['driver']['id'],
                ],
                [
                    'data' => json_encode($earnings),
                ]
            );

            // Ultimo saldo ja com recibos abatidos
            $last = DriversBalance::where('driver_id', $data['driver']['id'])
                ->where('tvde_week_id', '<', $data['tvde_week_id'])
                ->orderBy('tvde_week_id', 'desc')
                ->value('balance');

            $last = (float) ($last ?? 0.0);
            $balance = $last + $total;

            // Novo saldo
            DriversBalance::updateOrCreate(
                [
                    'driver_id'    => $data['driver']['id'],
                    'tvde_week_id' => $data['tvde_week_id'],
                ],
                [
                    'value'           => $total,
                    'balance'         => $balance,
                    'drivers_balance' => $last,
                ]
            );

            // recalcula semanas futuras (se existirem) com o novo carry
            DriversBalance::applyAdjustmentFromWeek($data['driver']['id'], $data['tvde_week_id'], 0);

            /*
        $email = $data['driver']['email'];

        Notification::route('mail', $email)
            ->notify(new ActivityLaunchesSend());
        */
        }
    }

    public function revalidateData(Request $request)
    {
        $driver_id = $request->driver_id;
        $company_id = Driver::find($driver_id)->company_id;
        $tvde_week_id = $request->tvde_week_id;
        $data = $request->data;

        $current_account = CurrentAccount::where([
            'tvde_week_id' => $tvde_week_id,
            'driver_id' => $driver_id
        ])->first();
        $results = $this->getWeekReport($company_id, $tvde_week_id);
        $driversById = collect($results['drivers'] ?? [])->keyBy('id');
        $serverDriver = $driversById->get($driver_id);
        $detailsPayload = $this->buildFuelDetailsPayload($serverDriver);

        $payload = is_array($data) ? $data : (array) $data;
        if (!empty($detailsPayload)) {
            $payload = array_merge($payload, $detailsPayload);
        }

        $current_account->data = json_encode($payload);
        $current_account->save();

        $last_balance = DriversBalance::where('driver_id', $data['driver']['id'])
            ->where('tvde_week_id', '<', $tvde_week_id)
            ->orderBy('tvde_week_id', 'desc')
            ->value('balance');

        $last_balance = (float) ($last_balance ?? 0);

        DriversBalance::updateOrCreate(
            [
                'driver_id'    => $data['driver']['id'],
                'tvde_week_id' => $data['tvde_week_id'],
            ],
            [
                'value'           => $data['driver']['total'],
                'drivers_balance' => $last_balance,
                'balance'         => $last_balance + $data['driver']['total'],
            ]
        );

        DriversBalance::applyAdjustmentFromWeek($data['driver']['id'], $data['tvde_week_id'], 0);
    }

    public function deleteData($tvde_week_id, $driver_id)
    {

        $current_account = CurrentAccount::where([
            'tvde_week_id' => $tvde_week_id,
            'driver_id' => $driver_id
        ])->first();

        if ($current_account) {
            $current_account->delete();
        }

        DriversBalance::where([
            'tvde_week_id' => $tvde_week_id,
            'driver_id' => $driver_id
        ])->delete();
        $nextWeek = DriversBalance::where('driver_id', $driver_id)
            ->where('tvde_week_id', '>', $tvde_week_id)
            ->orderBy('tvde_week_id')
            ->value('tvde_week_id');

        if ($nextWeek) {
            DriversBalance::applyAdjustmentFromWeek($driver_id, $nextWeek, 0);
        }

        return redirect()->route('admin.company-reports.index')->with('message', 'Data deleted successfully.');
    }

    protected function buildFuelDetailsPayload($driver): array
    {
        if (!$driver) {
            return [];
        }

        $details = data_get($driver, 'earnings.details', []);
        if (empty($details) || !is_array($details)) {
            return [];
        }

        $payload = [];

        $fuelItems = $details['fuel'] ?? [];
        if (!empty($fuelItems) && is_array($fuelItems)) {
            $mapped = [];
            foreach ($fuelItems as $item) {
                $date = data_get($item, 'date');
                $amount = data_get($item, 'total', data_get($item, 'amount', 0));
                if ($date === null) {
                    continue;
                }
                $mapped[] = [
                    'date' => $date,
                    'source' => 'PRIO',
                    'amount' => (float) $amount,
                ];
            }
            if (!empty($mapped)) {
                $payload['fuel_transactions_details'] = $mapped;
            }
        }

        $teslaItems = $details['tesla'] ?? [];
        if (!empty($teslaItems) && is_array($teslaItems)) {
            $mapped = [];
            foreach ($teslaItems as $item) {
                $date = data_get($item, 'date');
                $amount = data_get($item, 'total', data_get($item, 'amount', 0));
                if ($date === null) {
                    continue;
                }
                $mapped[] = [
                    'date' => $date,
                    'source' => 'TESLA',
                    'amount' => (float) $amount,
                ];
            }
            if (!empty($mapped)) {
                $payload['tesla_charging_details'] = $mapped;
            }
        }

        $viaVerdeItems = $details['via_verde'] ?? [];
        if (!empty($viaVerdeItems) && is_array($viaVerdeItems)) {
            $mapped = [];
            foreach ($viaVerdeItems as $item) {
                $date = data_get($item, 'date');
                $amount = data_get($item, 'total', data_get($item, 'amount', 0));
                if ($date === null) {
                    continue;
                }
                $mapped[] = [
                    'date' => $date,
                    'amount' => (float) $amount,
                ];
            }
            if (!empty($mapped)) {
                $payload['via_verde_details'] = $mapped;
            }
        }

        return $payload;
    }

    public function driverReportAllWeeks($driver_id = NULL, $state_id = 1)
    {

        $drivers = Driver::where('state_id', $state_id)->get();
        $driver_id = $driver_id ?? $drivers->first()->id;

        $weeks = \App\Models\TvdeWeek::orderBy('start_date', 'desc')->get();

        $results = [];

        foreach ($weeks as $week) {
            $account = \App\Models\CurrentAccount::where([
                'tvde_week_id' => $week->id,
                'driver_id' => $driver_id
            ])->first();

            $balance = \App\Models\DriversBalance::where([
                'tvde_week_id' => $week->id,
                'driver_id' => $driver_id
            ])->first();

            $receipt = \App\Models\Receipt::where([
                'driver_id'    => $driver_id,
                'tvde_week_id' => $week->id,
            ])->latest()->first();

            // ➜ Devoluções validadas (motorista → empresa) nesta semana
            $reimbursed = Reimbursement::where([
                'driver_id'    => $driver_id,
                'tvde_week_id' => $week->id,
                'verified'     => 1,                 // só as validadas
            ])->sum('value');

            $data = $account ? json_decode($account->data) : null;

            $amount_transferred = ($receipt->amount_transferred ?? 0) - $reimbursed;
            $adjustments_excluding_car_hire = 0;
            if ($data) {
                if (property_exists($data, 'adjustments_excluding_car_hire')) {
                    $adjustments_excluding_car_hire = (float) $data->adjustments_excluding_car_hire;
                } elseif (!empty($data->adjustments_array)) {
                    foreach ($data->adjustments_array as $adj) {
                        $is_car_hire = is_array($adj) ? ($adj['car_hire_deduct'] ?? false) : ($adj->car_hire_deduct ?? false);
                        if ($is_car_hire) {
                            continue;
                        }
                        $type = is_array($adj) ? ($adj['type'] ?? '') : ($adj->type ?? '');
                        $amount = (float) (is_array($adj) ? ($adj['amount'] ?? 0) : ($adj->amount ?? 0));
                        $adjustments_excluding_car_hire += ($type === 'deduct') ? -$amount : $amount;
                    }
                } else {
                    $adjustments_excluding_car_hire = (float) ($data->adjustments ?? 0);
                }
            }

            $results[] = [
                'week' => $week,
                'uber_gross' => $data->uber->uber_gross ?? 0,
                'bolt_gross' => $data->bolt->bolt_gross ?? 0,
                'uber_net' => $data->uber->uber_net ?? 0,
                'bolt_net' => $data->bolt->bolt_net ?? 0,
                'total_gross' => $data->total_gross ?? 0,
                'total_net' => $data->total_net ?? 0,
                'adjustments' => $data->adjustments ?? 0,
                'adjustments_excluding_car_hire' => $adjustments_excluding_car_hire,
                'total' => $data->total ?? 0,
                'vat_value' => $data->vat_value ?? 0,
                'car_track' => $data->car_track ?? 0,
                'car_hire' => $data->car_hire ?? 0,
                'fuel_transactions' => $data->fuel_transactions ?? 0,
                'driver_balance' => $balance->balance ?? 0,
                'amount_transferred'   => $amount_transferred,
            ];
        }

        return view('admin.companyReports.driverReportAllWeeks')->with([
            'drivers' => $drivers,
            'driver_id' => $driver_id,
            'results' => $results,
            'state_id' => $state_id
        ]);
    }
}

