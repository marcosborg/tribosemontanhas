<?php

namespace App\Http\Requests;

use App\Models\VehicleItem;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateVehicleItemRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('vehicle_item_edit');
    }

    public function rules()
    {
        return [
            'vehicle_brand_id' => [
                'required',
                'integer',
            ],
            'vehicle_model_id' => [
                'required',
                'integer',
            ],
            'year' => [
                'string',
                'required',
            ],
            'license_plate' => [
                'string',
                'required',
            ],
            'vin' => [
                'string',
                'nullable',
            ],
            'documents' => [
                'array',
            ],
            'green_card_expires_at' => [
                'date',
                'nullable',
            ],
            'private_conditions_expires_at' => [
                'date',
                'nullable',
            ],
            'inspection_expires_at' => [
                'date',
                'nullable',
            ],
            'dua_expires_at' => [
                'date',
                'nullable',
            ],
            'fire_extinguisher_expires_at' => [
                'date',
                'nullable',
            ],
            'emel_expires_at' => [
                'date',
                'nullable',
            ],
            'cartrack_expires_at' => [
                'date',
                'nullable',
            ],
            'tesla_videos_expires_at' => [
                'date',
                'nullable',
            ],
        ];
    }
}
