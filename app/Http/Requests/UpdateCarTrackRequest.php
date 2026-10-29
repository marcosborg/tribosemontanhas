<?php

namespace App\Http\Requests;

use App\Models\CarTrack;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateCarTrackRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('car_track_edit');
    }

    public function rules()
    {
        return [
            'date' => ['required', 'date_format:Y-m-d H:i:s'],
            'value' => ['required', 'numeric'],
            'destination' => ['required', 'in:driver,company'],
            'reason' => ['nullable', 'required_if:destination,company', 'in:management_vehicle,personal_usage,annual_fee'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'tvde_week_id' => [
                'required',
                'integer',
            ],
            'license_plate' => [
                'string',
                'nullable',
            ],
        ];
    }
}
