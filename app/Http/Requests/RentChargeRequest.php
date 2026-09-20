<?php

namespace App\Http\Requests;

use App\Models\RentCharge;
use Illuminate\Foundation\Http\FormRequest;

class RentChargeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'exists:units,id'],
            'month' => ['required', 'date', $this->monthIsFree(...)],
            'amount' => ['required', 'numeric', 'min:0'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'due_on' => ['nullable', 'date'],
            'paid_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Kolumna `month` bywa zapisana z godziną, więc unikalność sprawdzamy po dacie,
     * a nie zwykłą regułą unique.
     */
    protected function monthIsFree(string $attribute, mixed $value, \Closure $fail): void
    {
        $taken = RentCharge::query()
            ->where('unit_id', $this->input('unit_id'))
            ->whereDate('month', $value)
            ->when($this->route('rent_charge'), fn ($query, $charge) => $query->whereKeyNot($charge->getKey()))
            ->exists();

        if ($taken) {
            $fail('Czynsz za ten miesiąc jest już naliczony dla tego lokalu.');
        }
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('month')) {
            $this->merge(['month' => date('Y-m-01', strtotime($this->input('month')))]);
        }

        // Odznaczenie „zapłacony” ma czyścić datę, żeby nie zostawała po poprzednim zapisie.
        if (! $this->boolean('is_paid')) {
            $this->merge(['paid_on' => null]);
        } elseif (! $this->filled('paid_on')) {
            $this->merge(['paid_on' => now()->toDateString()]);
        }
    }

    public function attributes(): array
    {
        return [
            'unit_id' => 'lokal',
            'month' => 'miesiąc',
            'amount' => 'kwota czynszu',
            'invoice_number' => 'numer faktury',
            'due_on' => 'termin płatności',
            'paid_on' => 'data zapłaty',
            'note' => 'uwagi',
        ];
    }
}
