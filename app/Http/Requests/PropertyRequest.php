<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PropertyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address' => ['required', 'string', 'max:255'],
            'total_area' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nazwa',
            'description' => 'opis',
            'address' => 'adres',
            'total_area' => 'powierzchnia',
        ];
    }
}
