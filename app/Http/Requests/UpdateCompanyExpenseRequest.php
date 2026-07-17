<?php

namespace App\Http\Requests;

use App\Models\CompanyExpense;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateCompanyExpenseRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('company_expense_edit');
    }

    public function rules()
    {
        return (new StoreCompanyExpenseRequest())->rules();
    }
}
