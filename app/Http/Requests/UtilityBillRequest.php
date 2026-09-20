<?php

namespace App\Http\Requests;

use App\Enums\UtilityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UtilityBillRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(UtilityType::class)],
            'invoice_number' => ['required', 'string', 'max:255'],
            'base_net_price' => ['required', 'numeric', 'min:0'],
            'margin_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consumption_value' => ['nullable', 'numeric', 'min:0'],
            'net_amount' => ['nullable', 'numeric', 'min:0'],
            'month' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('month')) {
            $this->merge(['month' => date('Y-m-01', strtotime($this->input('month')))]);
        }

        $this->merge(['margin_percent' => $this->input('margin_percent') ?: 0]);
    }

    public function attributes(): array
    {
        return [
            'type' => 'rodzaj',
            'invoice_number' => 'numer faktury',
            'base_net_price' => 'cena netto z faktury',
            'margin_percent' => 'marża',
            'consumption_value' => 'wartość zużycia',
            'net_amount' => 'kwota netto',
            'month' => 'miesiąc',
        ];
    }
}
