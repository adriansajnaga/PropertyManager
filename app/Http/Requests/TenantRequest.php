<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:10'],
            'street' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function attributes(): array
    {
        return [
            'name' => 'nazwa',
            'nip' => 'NIP',
            'email' => 'adres e-mail',
            'phone' => 'telefon',
            'city' => 'miasto',
            'zip' => 'kod pocztowy',
            'street' => 'ulica',
            'is_active' => 'aktywny',
        ];
    }
}
