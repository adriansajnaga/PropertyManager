<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnitRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'exists:properties,id'],
            'description' => ['required', 'string', 'max:255'],
            'area' => ['required', 'numeric', 'min:0.01'],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_paid_on' => ['nullable', 'date'],
            'settles_utilities' => ['boolean'],
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'valid_from' => ['nullable', 'date'],
            'meters' => ['array'],
            'meters.water' => ['nullable', 'exists:meters,id'],
            'meters.electric' => ['nullable', 'exists:meters,id'],
            'meters.heat' => ['nullable', 'exists:meters,id'],
        ];
    }

    /** W formularzu pytamy przecząco („nie rozliczaj"), w bazie trzymamy twierdząco. */
    protected function prepareForValidation(): void
    {
        $this->merge(['settles_utilities' => ! $this->boolean('skip_utilities')]);
    }

    public function attributes(): array
    {
        return [
            'property_id' => 'nieruchomość',
            'description' => 'opis',
            'area' => 'powierzchnia',
            'rent_amount' => 'czynsz miesięczny',
            'deposit_amount' => 'kaucja',
            'deposit_paid_on' => 'data wpłaty kaucji',
            'settles_utilities' => 'rozliczanie mediów',
            'tenant_id' => 'najemca',
            'valid_from' => 'obowiązuje od',
        ];
    }
}
