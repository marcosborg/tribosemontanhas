<?php

namespace App\Http\Requests;

use App\Models\CompanyExpense;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreCompanyExpenseRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('company_expense_create');
    }

    public function rules()
    {
        return [
            'expense_mode' => [
                'required',
                'in:recurring,accounting',
            ],
            'name' => [
                'string',
                'required_if:expense_mode,recurring',
                'nullable',
            ],
            'company_id' => [
                'required',
                'integer',
            ],
            'weekly_value' => [
                'required_if:expense_mode,recurring',
                'nullable',
                'numeric',
                'min:0',
            ],
            'start_date' => [
                'required_if:expense_mode,recurring',
                'nullable',
                'date_format:' . config('panel.date_format'),
            ],
            'end_date' => [
                'required_if:expense_mode,recurring',
                'nullable',
                'date_format:' . config('panel.date_format'),
            ],
            'qty' => [
                'required_if:expense_mode,recurring',
                'nullable',
                'integer',
                'min:0',
            ],
            'expense_type' => ['required_if:expense_mode,accounting', 'nullable', 'string'],
            'date' => ['required_if:expense_mode,accounting', 'nullable', 'date_format:' . config('panel.date_format')],
            'description' => ['nullable', 'string'],
            'value' => ['required_if:expense_mode,accounting', 'nullable', 'numeric', 'min:0'],
            'invoice_value' => ['nullable', 'numeric', 'min:0'],
            'vat' => ['required_if:expense_mode,accounting', 'nullable', 'numeric', 'min:0'],
            'is_paid' => ['nullable', 'boolean'],
            'payment_reference' => ['nullable', 'string'],
            'pay_to' => ['nullable', 'string'],
            'files' => ['array'],
            'documents' => ['array'],
            'documents.*' => ['file', 'max:10240'],
        ];
    }
}
