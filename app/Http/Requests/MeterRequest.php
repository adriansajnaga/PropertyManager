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
            'is_module' => ['boolean'],
            'module_for_meter_id' => array_filter([
                $this->boolean('is_module') ? 'required' : 'nullable',
                // Domknięcie zamiast ->where('is_module', false): wartość logiczna
                // w regule „exists" nie trafia poprawnie do zapytania.
                Rule::exists('meters', 'id')->where(
                    fn ($query) => $query->where('type', $this->input('type'))->where('is_module', false),
                ),
                $meterId ? Rule::notIn([$meterId]) : null,
            ]),
            'module_offset' => ['nullable', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_main' => $this->boolean('is_main'),
            'is_boiler_supply' => $this->boolean('is_boiler_supply'),
            'is_module' => $this->boolean('is_module'),
            'module_offset' => $this->boolean('is_module') ? ($this->input('module_offset') ?: 0) : 0,
            'module_for_meter_id' => $this->boolean('is_module') ? $this->input('module_for_meter_id') : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'module_for_meter_id.required' => 'Wskaż licznik, na którym zamontowana jest ta nakładka.',
            'module_for_meter_id.exists' => 'Licznik pod nakładką musi być licznikiem tego samego medium i sam nie może być nakładką.',
            'module_for_meter_id.not_in' => 'Nakładka nie może być przypisana sama do siebie.',
        ];
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
            'is_module' => 'nakładka radiowa',
            'module_for_meter_id' => 'licznik pod nakładką',
            'module_offset' => 'różnica wskazań',
        ];
    }
}
