<?php

namespace App\Http\Requests;

use App\Enums\MeterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeterRequest extends FormRequest
{
    public function rules(): array
    {
        $meterId = $this->route('meter')?->id;

        return [
            'type' => ['required', Rule::enum(MeterType::class)],
            'serial_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('meters')->where('type', $this->input('type'))->ignore($meterId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_main' => ['boolean'],
            'is_boiler_supply' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_main' => $this->boolean('is_main'),
            'is_boiler_supply' => $this->boolean('is_boiler_supply'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'type' => 'typ',
            'serial_number' => 'numer seryjny',
            'name' => 'nazwa',
            'model' => 'model',
            'is_active' => 'aktywny',
            'is_main' => 'licznik główny',
        ];
    }
}
