<?php

namespace App\Http\Requests;

use App\Models\VehicleExpense;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreVehicleExpenseRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('vehicle_expense_create');
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
            'is_group_expense' => [
                'nullable',
                'boolean',
            ],
            'vehicle_item_ids' => [
                'required_if:is_group_expense,1',
                'array',
                'nullable',
                'min:2',
            ],
            'vehicle_item_ids.*' => [
                'integer',
                'exists:vehicle_items,id',
                'distinct',
            ],
            'vehicle_values' => [
                'required_if:is_group_expense,1',
                'array',
                'nullable',
            ],
            'vehicle_values.*' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'group_label' => [
                'string',
                'nullable',
            ],
            'value' => [
                'required_unless:is_group_expense,1',
                'nullable',
                'numeric',
                'min:0',
            ],
            'vat' => [
                'numeric',
                'required',
            ],
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
