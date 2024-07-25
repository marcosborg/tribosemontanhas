<?php

namespace App\Http\Controllers\Admin;

use App\Models\Driver;
use App\Models\TvdeActivity;
use App\Http\Controllers\Traits\Reports;
use App\Models\CurrentAccount;
use App\Models\DriversBalance;
use Gate;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use Illuminate\Http\Request;
use App\Models\CompanyInvoice;
use App\Models\CompanyData;

class HomeController
{

    use Reports;
    use MediaUploadingTrait;

    public function index()
    {

        return view('home');
    }

    public function selectCompany($company_id)
    {
        session()->forget('driver_id');
        session()->put('company_id', $company_id);
    }

    public function companyDashboard()
    {
        abort_if(Gate::denies('weekly_expense_report_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if (auth()->user()->hasRole('Empresas Associadas')) {
            $user = auth()->user()->load('company');
            $company_id = $user->company->id;
            session()->put('company_id', $company_id);
        }

        $filter = $this->filter();
        $company_id = $filter['company_id'];
        $tvde_week_id = $filter['tvde_week_id'];
        $tvde_week = $filter['tvde_week'];
        $tvde_years = $filter['tvde_years'];
        $tvde_year_id = $filter['tvde_year_id'];
        $tvde_months = $filter['tvde_months'];
        $tvde_month_id = $filter['tvde_month_id'];
        $tvde_weeks = $filter['tvde_weeks'];

        //COMPANY EXPENSES

        $company_data = CompanyData::where([
            'company_id' => $company_id,
            'tvde_week_id' => $tvde_week_id
        ])->first();

        if ($company_data) {
            $data = json_decode($company_data->data);
        } else {
            $this->saveCompanyExpenses($company_id, $tvde_week_id);
            return redirect(url()->current());
        }

        return view('admin.weeklyExpenseReports.index')->with([
            'company_id' => $company_id,
            'tvde_years' => $tvde_years,
            'tvde_year_id' => $tvde_year_id,
            'tvde_months' => $tvde_months,
            'tvde_month_id' => $tvde_month_id,
            'tvde_weeks' => $tvde_weeks,
            'tvde_week_id' => $tvde_week_id,
            'company_expenses' => $data->company_expenses,
            'total_company_expenses' => $data->total_company_expenses,
            'totals' => $data->totals,
            'company_park' => $data->company_park,
            'final_total' => $data->final_total,
            'final_company_expenses' => $data->final_company_expenses,
            'profit' => $data->profit,
            'roi' => $data->roi,
            'total_consultancy' => $data->total_consultancy,
            'fleet_adjusments' => $data->fleet_adjusments,
            'fleet_consultancies' => $data->fleet_consultancies,
            'fleet_company_parks' => $data->fleet_company_parks,
            'fleet_earnings' => $data->fleet_earnings,
            'total_company_adjustments' => $data->totals->total_company_adjustments,
        ]);
    }

    public function companyInvoiceDashboard()
    {

        $company_id = auth()->user()->company->id;

        if (!session()->get('company_id')) {
            session()->put('company_id', $company_id);
        }

        $company = auth()->user()->company->load('company_invoices');

        if ($company->suspended) {
            session()->flush();
            return redirect('/login')->with('message', 'A sua conta está suspensa. Entre em contacto com a ' . env('APP_NAME'));
        }

        return view('admin.companyInvoiceDashboard.index', compact('company'));
    }

    public function companyInvoiceUploadMedia(Request $request)
    {
        $file = $this->storeMedia($request);
        $fileData = json_decode($file->content());
        $fileName = $fileData->name;
        $company_invoice = CompanyInvoice::find($request->company_invoice_id);
        $company_invoice->addMedia(storage_path('tmp/uploads/' . $fileName))->toMediaCollection('payment_receipt');
        return redirect()->back();
    }

}