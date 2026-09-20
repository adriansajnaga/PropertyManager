<?php

namespace App\Http\Requests;

use App\Services\ReadingLookup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReadingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'meter_id' => ['required', 'exists:meters,id'],
            'raw_hex' => ['nullable', 'string', 'max:255'],
            'consumption' => ['required', 'numeric', 'min:0'],
            'reading_date' => ['required', 'date'],
            'reading_timestamp' => ['nullable', 'date'],
        ];
    }

    /**
     * Stan licznika tylko rośnie — nowy odczyt musi mieścić się między
     * odczytem wcześniejszym a późniejszym (przy wpisie z datą wsteczną).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $lookup = app(ReadingLookup::class);
                $meterId = (int) $this->input('meter_id');
                $date = $this->date('reading_date')->toDateString();
                $value = (float) $this->input('consumption');
                $exceptId = $this->route('reading')?->id;

                $previous = $lookup->previousFor($meterId, $date, $exceptId);

                if ($previous !== null && $value < (float) $previous->consumption) {
                    $validator->errors()->add('consumption', sprintf(
                        'Odczyt nie może być mniejszy od poprzedniego: %s z dnia %s.',
                        number_format((float) $previous->consumption, 2, ',', ' '),
                        $previous->reading_date->format('d.m.Y'),
                    ));
                }

                $next = $lookup->nextFor($meterId, $date, $exceptId);

                if ($next !== null && $value > (float) $next->consumption) {
                    $validator->errors()->add('consumption', sprintf(
                        'Odczyt nie może być większy od późniejszego odczytu: %s z dnia %s.',
                        number_format((float) $next->consumption, 2, ',', ' '),
                        $next->reading_date->format('d.m.Y'),
                    ));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'meter_id' => 'licznik',
            'raw_hex' => 'wartość HEX',
            'consumption' => 'odczyt',
            'reading_date' => 'data odczytu',
            'reading_timestamp' => 'znacznik czasu',
        ];
    }
}
