<?php

namespace App\Http\Requests;

use App\Models\VehicleUsage;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateVehicleUsageRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('vehicle_usage_edit');
    }

    public function rules()
    {
        return [
            'vehicle_item_id' => [
                'required',
                'integer',
            ],
            'start_date' => [
                'required',
                'date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
