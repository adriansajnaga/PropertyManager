<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSetting;
use App\Support\InvoiceLogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoiceSettingController extends Controller
{
    public function edit()
    {
        return view('invoice-settings.edit', ['settings' => InvoiceSetting::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'seller_name' => ['required', 'string', 'max:255'],
            'seller_nip' => ['required', 'string', 'max:20'],
            'seller_regon' => ['nullable', 'string', 'max:20'],
            'seller_address_l1' => ['required', 'string', 'max:255'],
            'seller_address_l2' => ['required', 'string', 'max:255'],
            'seller_phone' => ['nullable', 'string', 'max:32'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'bank_swift' => ['nullable', 'string', 'max:16'],
            'issue_place' => ['nullable', 'string', 'max:255'],
            'payment_days' => ['required', 'integer', 'min:0', 'max:180'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'rent_is_gross' => ['boolean'],
            'line_description' => ['required', 'string', 'max:255'],
            'receipt_issuer_name' => ['nullable', 'string', 'max:255'],
            'receipt_address_l1' => ['nullable', 'string', 'max:255'],
            'receipt_address_l2' => ['nullable', 'string', 'max:255'],
            'receipt_identifier' => ['nullable', 'string', 'max:64'],
            'receipt_note' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:'.InvoiceLogo::ACCEPTED, 'max:2048'],
        ], [], [
            'seller_name' => 'nazwa sprzedawcy',
            'seller_nip' => 'NIP sprzedawcy',
            'seller_address_l1' => 'ulica i numer',
            'seller_address_l2' => 'kod pocztowy i miejscowość',
            'payment_days' => 'termin płatności',
            'vat_rate' => 'stawka VAT',
            'line_description' => 'opis pozycji',
        ]);

        $data['rent_is_gross'] = $request->boolean('rent_is_gross');
        $settings = InvoiceSetting::current();

        if ($request->hasFile('logo')) {
            // Logo trzymamy poza katalogiem publicznym — do PDF wchodzi jako dane, nie jako plik.
            if (filled($settings->logo_path)) {
                Storage::disk('local')->delete($settings->logo_path);
            }

            $data['logo_path'] = InvoiceLogo::store($request->file('logo'));
        }

        unset($data['logo']);

        $settings->fill($data)->save();

        return redirect()->route('invoice-settings.edit')
            ->with('status', 'Ustawienia faktur zostały zapisane.');
    }
}
