<?php

namespace App\Http\Requests;

use App\Models\VehicleExpense;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateVehicleExpenseRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('vehicle_expense_edit');
    }

    public function rules()
    {
        return [
            'expense_type' => [
                'required',
            ],
            'date' => [
                'required',
                'date_format:' . config('panel.date_format'),
            ],
            'files' => [
                'array',
            ],
            'value' => [
                'numeric',
                'min:0',
                'required',
            ],
            'final_value' => ['nullable', 'numeric', 'min:0'],
            'vehicle_final_values' => ['nullable', 'array'],
            'vehicle_final_values.*' => ['nullable', 'numeric', 'min:0'],
            'vat' => [
                'min:0',
                'numeric',
                'required',
            ],
            'paid_at' => ['nullable', 'date_format:Y-m-d'],
            'is_paid' => [
                'nullable',
                'boolean',
            ],
            'payment_reference' => [
                'string',
                'nullable',
            ],
            'pay_to' => [
                'string',
                'nullable',
            ],
        ];
    }
}
