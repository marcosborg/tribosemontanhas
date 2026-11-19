<?php

namespace App\Http\Requests;

use App\Models\Adjustment;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateAdjustmentRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('adjustment_edit');
    }

    public function rules()
    {
        return [
            'weekly_schedule' => ['sometimes', 'boolean'],
            'week_start' => ['required_if:weekly_schedule,1', 'nullable', 'date_format:Y-m-d'],
            'week_count' => ['required_if:weekly_schedule,1', 'nullable', 'integer', 'min:1', 'max:520'],
            'name' => [
                'string',
                'required',
            ],
            'type' => [
                'required',
            ],
            'percent' => [
                'string',
                'nullable',
            ],
            'start_date' => [
                'date_format:' . config('panel.date_format'),
                'nullable',
            ],
            'end_date' => [
                'date_format:' . config('panel.date_format'),
                'nullable',
            ],
            'drivers.*' => [
                'integer',
            ],
            'drivers' => [
                'array',
            ],
            'company_id' => [
                'required',
                'integer',
            ],
        ];
    }
}
