<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Adjustment;
use App\Models\Card;
use App\Models\CombustionTransaction;
use App\Models\Company;
use App\Models\ContractTypeRank;
use App\Models\Driver;
use App\Models\DriversBalance;
use App\Models\Electric;
use App\Models\ElectricTransaction;
use App\Models\TvdeActivity;
use App\Models\TvdeMonth;
use App\Models\TvdeWeek;
use App\Models\TvdeYear;
use App\Models\CurrentAccount;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Traits\Reports;

class FinancialStatementController extends Controller
{

    use Reports;

    public function index()
    {

        abort_if(Gate::denies('financial_statement_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $filter = $this->filter();
        $company_id = $filter['company_id'];
        $tvde_week_id = $filter['tvde_week_id'];
        $tvde_week = $filter['tvde_week'];
        $tvde_years = $filter['tvde_years'];
        $tvde_year_id = $filter['tvde_year_id'];
        $tvde_months = $filter['tvde_months'];
        $tvde_month_id = $filter['tvde_month_id'];
        $tvde_weeks = $filter['tvde_weeks'];
        $drivers = $filter['drivers'];

        $driver_id = session()->get('driver_id') ? session()->get('driver_id') : $driver_id = 0;

        if (!session()->has('company_id')) {
            $company_id = auth()->user()->company->id;
            session()->put('company_id', $company_id);
        }

        if ($driver_id != 0) {

            $driver = Driver::find($driver_id)->load([
                'contract_type.contract_type_ranks',
                'contract_vat',
                'team.drivers'
            ]);

            $results = CurrentAccount::where([
                'tvde_week_id' => $tvde_week_id,
                'driver_id' => $driver_id
            ])->first();

            if ($results) {
                $results = json_decode($results->data);
            } else {
                $total_earnings_uber = 0;
                $total_earnings_bolt = 0;
                $total_tips_uber = 0;
                $total_tips_bolt = 0;
                $total_earnings = 0;
                $total_earnings_no_tip = 0;
                $total_tips = 0;
                $gross_credits = 0;
            }

        } else {
            $driver = null;
            //COLLECT ALL DRIVER RESULTS
            $total_earnings_uber = [];
            $total_earnings_bolt = [];
            $total_tips_uber = [];
            $total_tips_bolt = [];
            $total_earnings = [];
            $total_earnings_no_tip = [];
            $total_tips = [];
            $gross_credits = [];

            foreach ($drivers as $driver) {
                $current = CurrentAccount::where([
                    'tvde_week_id' => $tvde_week_id,
                    'driver_id' => $driver->id
                ])->first();

                if ($current) {
                    $data = json_decode($current->data);
                    $total_earnings_uber[] = $data->total_earnings_uber;
                    $total_earnings_bolt[] = $data->total_earnings_bolt;
                    $total_tips_uber[] = $data->total_tips_uber;
                    $total_tips_bolt[] = $data->total_tips_bolt;
                    $total_earnings[] = $data->total_earnings;
                    $total_earnings_no_tip[] = $data->total_earnings_no_tip;
                    $total_tips[] = $data->total_tips;
                    $gross_credits[] = $data->gross_credits;
                }
            }

            $total_earnings_uber = array_sum($total_earnings_uber);
            $total_earnings_bolt = array_sum($total_earnings_bolt);
            $total_tips_uber = array_sum($total_tips_uber);
            $total_tips_bolt = array_sum($total_tips_bolt);
            $total_earnings = array_sum($total_earnings);
            $total_earnings_no_tip = array_sum($total_earnings_no_tip);
            $total_tips = array_sum($total_tips);
            $gross_credits = array_sum($gross_credits);
        }

        $total_earnings = isset($results) ? $results->total_earnings : $total_earnings ?? 0;
        $total_after_vat = isset($results) ? $results->total_after_vat : 0;
        $gross_debts = isset($results) ? $results->gross_debts : 0;
        $gross_credits = isset($results) ? $results->gross_credits : $gross_credits ?? 0;
        $final_total = isset($results) ? $results->final_total : 0;

        $team_gross_credits = $results->team_gross_credits ?? 0;
        $team_liquid_credits = $results->team_liquid_credits ?? 0;
        $team_final_total = $results->team_final_total ?? 0;
        $team_final_result = $results->team_final_result ?? 0;
        $team_results = $results->team_results ?? [];

        if ($team_gross_credits > 0) {
            $total_earnings = $total_earnings + $team_gross_credits;
            $total_after_vat = $total_after_vat + $team_liquid_credits;
            $gross_debts = $gross_debts + $team_final_total;
            $gross_credits = $gross_credits + $team_liquid_credits;
            $team_final_result = $team_liquid_credits - $team_final_total;
            $final_total = $gross_credits - $gross_debts;
        }

        //GRAFICOS

        $team_earnings = collect();

        foreach ($drivers as $key => $d) {
            $team_driver_bolt_earnings = TvdeActivity::where([
                'tvde_week_id' => $tvde_week_id,
                'tvde_operator_id' => 2,
                'driver_code' => $d->bolt_name
            ])
                ->get()->sum('earnings_two');

            $team_driver_uber_earnings = TvdeActivity::where([
                'tvde_week_id' => $tvde_week_id,
                'tvde_operator_id' => 1,
                'driver_code' => $d->uber_uuid
            ])
                ->get()->sum('earnings_two');

            $team_driver_earnings = $team_driver_bolt_earnings + $team_driver_uber_earnings;
            if ($driver) {
                $entry = collect([
                    'driver' => $driver->uber_uuid == $d->uber_uuid || $driver->bolt_name == $d->bolt_name ? $driver->name : 'Motorista ' . $key + 1,
                    'earnings' => sprintf("%.2f", $team_driver_earnings),
                    'own' => $driver->uber_uuid == $d->uber_uuid || $driver->bolt_name == $d->bolt_name
                ]);
                $team_earnings->add($entry);
            }
        }

        $driver_balance = DriversBalance::where([
            'driver_id' => $driver_id,
            'tvde_week_id' => $tvde_week_id
        ])->first();

        return view('admin.financialStatements.index')->with([
            'company_id' => $company_id,
            'tvde_year_id' => $tvde_year_id,
            'tvde_years' => $tvde_years,
            'tvde_months' => $tvde_months,
            'tvde_month_id' => $tvde_month_id,
            'tvde_weeks' => $tvde_weeks,
            'tvde_week_id' => $tvde_week_id,
            'drivers' => $drivers,
            'driver_id' => $driver_id,
            'total_earnings_uber' => isset($results) ? $results->total_earnings_uber : $total_earnings_uber ?? 0,
            'contract_type_rank' => isset($results) ? $results->contract_type_rank : 0,
            'total_uber' => isset($results) ? $results->total_uber : 0,
            'total_earnings_bolt' => isset($results) ? $results->total_earnings_bolt : $total_earnings_bolt ?? 0,
            'total_bolt' => isset($results) ? $results->total_bolt : 0,
            'total_tips_uber' => isset($results) ? $results->total_tips_uber : $total_tips_uber ?? 0,
            'uber_tip_percent' => isset($results) ? $results->uber_tip_percent : 0,
            'uber_tip_after_vat' => isset($results) ? $results->uber_tip_after_vat : 0,
            'total_tips_bolt' => isset($results) ? $results->total_tips_bolt : $total_tips_bolt ?? 0,
            'bolt_tip_percent' => isset($results) ? $results->bolt_tip_percent : 0,
            'bolt_tip_after_vat' => isset($results) ? $results->bolt_tip_after_vat : 0,
            'total_tips' => isset($results) ? $results->total_tips : $total_tips ?? 0,
            'total_tip_after_vat' => isset($results) ? $results->total_tip_after_vat : 0,
            'adjustments' => isset($results) ? $results->adjustments : null,
            'total_earnings' => $total_earnings,
            'total_earnings_no_tip' => isset($results) ? $results->total_earnings_no_tip : $total_earnings_no_tip ?? 0,
            'total' => isset($results) ? $results->total : 0,
            'total_after_vat' => $total_after_vat,
            'gross_credits' => $gross_credits,
            'gross_debts' => $gross_debts,
            'final_total' => $final_total,
            'driver' => isset($driver) ? $driver : null,
            'team_earnings' => $team_earnings,
            'electric_expenses' => isset($results) ? $results->electric_expenses : 0,
            'combustion_expenses' => isset($results) ? $results->combustion_expenses : 0,
            'combustion_racio' => isset($results) ? $results->combustion_racio : 0,
            'electric_racio' => isset($results) ? $results->electric_racio : 0,
            'total_earnings_after_vat' => isset($results) ? $results->total_earnings_after_vat : 0,
            'txt_admin' => isset($results) ? $results->txt_admin : 0,
            'driver_balance' => $driver_balance ?? null,
            'team_results' => $team_results ?? null,
            'team_final_total' => $team_final_total,
            'team_liquid_credits' => $team_liquid_credits,
            'team_final_result' => $team_final_result
        ]);
    }

    public function year($tvde_year_id)
    {
        session()->put('tvde_year_id', $tvde_year_id);
        session()->put('tvde_month_id', TvdeMonth::orderBy('number', 'desc')->where('year_id', session()->get('tvde_year_id'))->first()->id);
        session()->put('tvde_week_id', TvdeWeek::orderBy('number', 'desc')->where('tvde_month_id', session()->get('tvde_month_id'))->first()->id);
        return back();
    }

    public function month($tvde_month_id)
    {
        session()->put('tvde_month_id', $tvde_month_id);
        session()->put('tvde_week_id', TvdeWeek::orderBy('number', 'desc')->where('tvde_month_id', $tvde_month_id)->first()->id);
        return back();
    }

    public function week($tvde_week_id)
    {
        session()->put('tvde_week_id', $tvde_week_id);
        return back();
    }

    public function driver($driver_id)
    {
        session()->put('driver_id', $driver_id);
        return back();
    }

    public function pdf(Request $request)
    {
        abort_if(Gate::denies('financial-pdf'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tvde_week_id = session()->get('tvde_week_id');
        $driver_id = session()->get('driver_id');
        $company_id = session()->get('company_id');

        $driver = Driver::find($driver_id);
        $company = Company::find($company_id);

        $tvde_week = TvdeWeek::find($tvde_week_id);

        $bolt_activities = TvdeActivity::where([
            'tvde_week_id' => $tvde_week_id,
            'tvde_operator_id' => 2,
            'driver_code' => $driver->bolt_name,
            'company_id' => $company_id,
        ])
            ->get();

        $uber_activities = TvdeActivity::where([
            'tvde_week_id' => $tvde_week_id,
            'tvde_operator_id' => 1,
            'driver_code' => $driver->uber_uuid,
            'company_id' => $company_id,
        ])
            ->get();

        $adjustments = Adjustment::whereHas('drivers', function ($query) use ($driver_id) {
            $query->where('id', $driver_id);
        })
            ->where('company_id', $company_id)
            ->where(function ($query) use ($tvde_week) {
                $query->where('start_date', '<=', $tvde_week->start_date)
                    ->orWhereNull('start_date');
            })
            ->where(function ($query) use ($tvde_week) {
                $query->where('end_date', '>=', $tvde_week->end_date)
                    ->orWhereNull('end_date');
            })
            ->get();

        $refund = 0;
        $deduct = 0;

        foreach ($adjustments as $adjustment) {
            switch ($adjustment->type) {
                case 'refund':
                    $refund = $refund + $adjustment->amount;
                    break;
                case 'deduct':
                    $deduct = $deduct + $adjustment->amount;
                    break;
            }
        }

        // FUEL EXPENSES

        $electric_expenses = null;
        if ($driver && $driver->electric_id) {
            $electric = Electric::find($driver->electric_id);
            if ($electric) {
                $electric_transactions = ElectricTransaction::where([
                    'card' => $electric->code,
                    'tvde_week_id' => $tvde_week_id
                ])->get();
                $electric_expenses = collect([
                    'amount' => number_format($electric_transactions->sum('amount'), 2, '.', '') . ' kWh',
                    'total' => number_format($electric_transactions->sum('total'), 2, '.', '') . ' €',
                    'value' => $electric_transactions->sum('total')
                ]);
            }
        }
        $combustion_expenses = null;
        if ($driver && $driver->card_id) {
            $card = Card::find($driver->card_id);
            if (!$card) {
                $code = 0;
            } else {
                $code = $card->code;
            }
            $combustion_transactions = CombustionTransaction::where([
                'card' => $code,
                'tvde_week_id' => $tvde_week_id
            ])->get();
            $combustion_expenses = collect([
                'amount' => number_format($combustion_transactions->sum('amount'), 2, '.', '') . ' L',
                'total' => number_format($combustion_transactions->sum('total'), 2, '.', '') . ' €',
                'value' => $combustion_transactions->sum('total')
            ]);
        }

        $total_earnings_bolt = number_format($bolt_activities->sum('earnings_two') - $bolt_activities->sum('earnings_one'), 2, '.', '');
        $total_tips_bolt = number_format($bolt_activities->sum('earnings_one'), 2);
        $total_earnings_uber = number_format($uber_activities->sum('earnings_two') - $uber_activities->sum('earnings_one'), 2, '.', '');
        $total_tips_uber = number_format($uber_activities->sum('earnings_one'), 2);
        $total_tips = $total_tips_uber + $total_tips_bolt;
        $total_earnings = $bolt_activities->sum('earnings_two') + $uber_activities->sum('earnings_two');
        $total_earnings_no_tip = ($bolt_activities->sum('earnings_two') - $bolt_activities->sum('earnings_one')) + ($uber_activities->sum('earnings_two') - $uber_activities->sum('earnings_one'));

        //CHECK PERCENT
        $contract_type_ranks = $driver ? ContractTypeRank::where('contract_type_id', $driver->contract_type_id)->get() : [];
        $contract_type_rank = count($contract_type_ranks) > 0 ? $contract_type_ranks[0] : null;
        foreach ($contract_type_ranks as $value) {
            if ($value->from <= $total_earnings && $value->to >= $total_earnings) {
                $contract_type_rank = $value;
            }
        }
        //

        $total_bolt = number_format(($bolt_activities->sum('earnings_two') - $bolt_activities->sum('earnings_one')) * ($contract_type_rank ? $contract_type_rank->percent / 100 : 0), 2, '.', '');
        $total_uber = number_format(($uber_activities->sum('earnings_two') - $uber_activities->sum('earnings_one')) * ($contract_type_rank ? $contract_type_rank->percent / 100 : 0), 2, '.', '');

        $total_earnings_after_vat = $total_bolt + $total_uber;

        $bolt_tip_percent = $driver ? 100 - $driver->contract_vat->tips : 100;
        $uber_tip_percent = $driver ? 100 - $driver->contract_vat->tips : 100;

        $bolt_tip_after_vat = number_format($total_tips_bolt * ($bolt_tip_percent / 100), 2);
        $uber_tip_after_vat = number_format($total_tips_uber * ($uber_tip_percent / 100), 2);

        $total_tip_after_vat = $bolt_tip_after_vat + $uber_tip_after_vat;

        $total = $total_earnings + $total_tips;
        $total_after_vat = $total_earnings_after_vat + $total_tip_after_vat;

        $gross_credits = $total_earnings_no_tip + $total_tips + $refund;
        $gross_debts = ($total_earnings_no_tip - $total_earnings_after_vat) + ($total_tips - $total_tip_after_vat) + $deduct;

        $final_total = $gross_credits - $gross_debts;

        $electric_racio = null;
        $combustion_racio = null;

        if ($electric_expenses && $total_earnings > 0) {
            $final_total = $final_total - $electric_expenses['value'];
            $gross_debts = $gross_debts + $electric_expenses['value'];
            if ($electric_expenses['value'] > 0) {
                $electric_racio = ($electric_expenses['value'] / $total_earnings) * 100;
            } else {
                $electric_racio = 0;
            }
        }
        if ($combustion_expenses && $total_earnings > 0) {
            $final_total = $final_total - $combustion_expenses['value'];
            $gross_debts = $gross_debts + $combustion_expenses['value'];
            if ($combustion_expenses['value'] > 0) {
                $combustion_racio = ($combustion_expenses['value'] / $total_earnings) * 100;
            } else {
                $combustion_racio = 0;
            }
        }

        if ($driver->contract_vat->percent && $driver->contract_vat->percent > 0) {
            $txt_admin = ($final_total * $driver->contract_vat->percent) / 100;
            $gross_debts = $gross_debts + $txt_admin;
            $final_total = $final_total - $txt_admin;
        } else {
            $txt_admin = 0;
        }

        //GRAFICOS

        $drivers = Driver::where('company_id', $company_id)->get();

        $team_earnings = collect();

        foreach ($drivers as $key => $d) {
            $team_driver_bolt_earnings = TvdeActivity::where([
                'tvde_week_id' => $tvde_week_id,
                'tvde_operator_id' => 2,
                'driver_code' => $d->bolt_name
            ])
                ->get()->sum('earnings_two');

            $team_driver_uber_earnings = TvdeActivity::where([
                'tvde_week_id' => $tvde_week_id,
                'tvde_operator_id' => 1,
                'driver_code' => $d->uber_uuid
            ])
                ->get()->sum('earnings_two');

            $team_driver_earnings = $team_driver_bolt_earnings + $team_driver_uber_earnings;
            if ($driver) {
                $entry = collect([
                    'driver' => $driver->uber_uuid == $d->uber_uuid || $driver->bolt_name == $d->bolt_name ? $driver->name : 'Motorista ' . $key + 1,
                    'earnings' => sprintf("%.2f", $team_driver_earnings),
                    'own' => $driver->uber_uuid == $d->uber_uuid || $driver->bolt_name == $d->bolt_name
                ]);
                $team_earnings->add($entry);
            }

            $labels = [];
            $earnings = [];
            $backgrounds = [];

            foreach ($team_earnings as $entry) {
                $labels[] = $entry['driver'];
                $earnings[] = $entry['earnings'];
                if ($entry['own']) {
                    $backgrounds[] = '#605ca8';
                } else {
                    $backgrounds[] = '#00a65a94';
                }
            }

        }

        $chart1 = "https://quickchart.io/chart?c={type:'bar',data:{labels:" . json_encode($labels) . ",datasets:[{borderWidth: 1, label:'Valor faturado',data:" . json_encode($earnings) . "}]}}";
        $chart2 = "https://quickchart.io/chart?c={type:'doughnut',data:{labels:['UBER', 'BOLT', 'GORJETAS'],datasets:[{label: 'Valor faturado', data: [" . $total_earnings_uber . ", " . $total_earnings_bolt . ", " . $total_tips . "]}]}}";

        /*

        return view('admin.financialStatements.pdf', compact([
            'company_id',
            'company',
            'tvde_week_id',
            'tvde_week',
            'driver_id',
            'bolt_activities',
            'uber_activities',
            'total_earnings_uber',
            'contract_type_rank',
            'total_uber',
            'total_earnings_bolt',
            'total_bolt',
            'total_tips_uber',
            'uber_tip_percent',
            'uber_tip_after_vat',
            'total_tips_bolt',
            'bolt_tip_percent',
            'bolt_tip_after_vat',
            'total_tips',
            'total_tip_after_vat',
            'adjustments',
            'total_earnings',
            'total_earnings_no_tip',
            'total',
            'total_after_vat',
            'gross_credits',
            'gross_debts',
            'final_total',
            'driver',
            'electric_expenses',
            'combustion_expenses',
            'combustion_racio',
            'electric_racio',
            'total_earnings_after_vat',
            'team_earnings',
            'txt_admin',
            'chart1',
            'chart2',
        ]));

        */

        $pdf = Pdf::loadView('admin.financialStatements.pdf', [
            'company_id' => $company_id,
            'company' => $company,
            'tvde_week_id' => $tvde_week_id,
            'tvde_week' => $tvde_week,
            'driver_id' => $driver_id,
            'bolt_activities' => $bolt_activities,
            'uber_activities' => $uber_activities,
            'total_earnings_uber' => $total_earnings_uber,
            'contract_type_rank' => $contract_type_rank,
            'total_uber' => $total_uber,
            'total_earnings_bolt' => $total_earnings_bolt,
            'total_bolt' => $total_bolt,
            'total_tips_uber' => $total_tips_uber,
            'uber_tip_percent' => $uber_tip_percent,
            'uber_tip_after_vat' => $uber_tip_after_vat,
            'total_tips_bolt' => $total_tips_bolt,
            'bolt_tip_percent' => $bolt_tip_percent,
            'bolt_tip_after_vat' => $bolt_tip_after_vat,
            'total_tips' => $total_tips,
            'total_tip_after_vat' => $total_tip_after_vat,
            'adjustments' => $adjustments,
            'total_earnings' => $total_earnings,
            'total_earnings_no_tip' => $total_earnings_no_tip,
            'total' => $total,
            'total_after_vat' => $total_after_vat,
            'gross_credits' => $gross_credits,
            'gross_debts' => $gross_debts,
            'final_total' => $final_total,
            'driver' => $driver,
            'electric_expenses' => $electric_expenses,
            'combustion_expenses' => $combustion_expenses,
            'combustion_racio' => $combustion_racio,
            'electric_racio' => $electric_racio,
            'total_earnings_after_vat' => $total_earnings_after_vat,
            'txt_admin' => $txt_admin,
            'team_earnings' => $team_earnings,
            'chart1' => $chart1,
            'chart2' => $chart2,
        ])->setOption([
                    'isRemoteEnabled' => true,
                ]);


        if ($request->download) {

            $filename = strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9\-]/', '', $driver->name . '-' . $tvde_week->start_date))) . '.pdf';

            return $pdf->download($filename);
        } else {
            return $pdf->stream();
        }

    }

    public function updateBalance(Request $request)
    {
        $request->validate([
            'balance' => 'required|numeric'
        ], [], [
            'balance' => 'Saldo'
        ]);

        $drivers_balance = DriversBalance::find($request->driver_balance_id);
        $drivers_balance->balance = $request->balance;
        $drivers_balance->drivers_balance = $request->balance;
        $drivers_balance->save();
    }

}