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
                'required',
            ],
            'vat' => [
                'numeric',
                'required',
            ],
        ];
    }
}
