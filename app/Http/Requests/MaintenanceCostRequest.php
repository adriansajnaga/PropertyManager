<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceCostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'exists:properties,id'],
            'description' => ['required', 'string', 'max:255'],
            'annual_cost' => ['required', 'numeric', 'min:0'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'property_id' => 'nieruchomość',
            'description' => 'opis kosztu',
            'annual_cost' => 'roczny koszt',
            'year' => 'rok',
        ];
    }
}
