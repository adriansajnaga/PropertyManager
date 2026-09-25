<?php

namespace App\Services\Ksef;

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Buduje XML faktury w schemie FA(3). Kolejność elementów jest istotna — XSD
 * opisuje je jako sekwencję — więc odwzorowuje dokument wystawiony w aplikacji
 * podatnika KSeF, który przeszedł walidację.
 */
class Fa3InvoiceBuilder
{
    private const NAMESPACE = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    /** Przelew — kod formy płatności w schemie FA. */
    private const PAYMENT_TRANSFER = '6';

    public function build(Invoice $invoice): string
    {
        $settings = InvoiceSetting::current();
        $tenant = $invoice->tenant;

        if (! $settings->isConfigured()) {
            throw new RuntimeException('Uzupełnij dane sprzedawcy w ustawieniach faktur.');
        }

        if ($tenant === null || blank($tenant->nip)) {
            throw new RuntimeException('Nabywca musi mieć numer NIP — uzupełnij kartotekę najemcy.');
        }

        $document = new DOMDocument('1.0', 'utf-8');
        $document->formatOutput = true;

        $root = $document->createElementNS(self::NAMESPACE, 'Faktura');
        $document->appendChild($root);

        $this->header($document, $root);
        $this->seller($document, $root, $settings);
        $this->buyer($document, $root, $tenant);
        $this->invoice($document, $root, $invoice, $settings);

        return $document->saveXML();
    }

    private function header(DOMDocument $document, DOMElement $root): void
    {
        $header = $this->child($document, $root, 'Naglowek');

        $formCode = $document->createElement('KodFormularza', 'FA');
        $formCode->setAttribute('kodSystemowy', 'FA (3)');
        $formCode->setAttribute('wersjaSchemy', '1-0E');
        $header->appendChild($formCode);

        $this->child($document, $header, 'WariantFormularza', '3');
        $this->child($document, $header, 'DataWytworzeniaFa', now()->utc()->format('Y-m-d\TH:i:s.v\Z'));
        $this->child($document, $header, 'SystemInfo', config('app.name'));
    }

    private function seller(DOMDocument $document, DOMElement $root, InvoiceSetting $settings): void
    {
        $seller = $this->child($document, $root, 'Podmiot1');
        $this->child($document, $seller, 'PrefiksPodatnika', 'PL');

        $identity = $this->child($document, $seller, 'DaneIdentyfikacyjne');
        $this->child($document, $identity, 'NIP', $this->digits($settings->seller_nip));
        $this->child($document, $identity, 'Nazwa', $settings->seller_name);

        $address = $this->child($document, $seller, 'Adres');
        $this->child($document, $address, 'KodKraju', 'PL');
        $this->child($document, $address, 'AdresL1', $settings->seller_address_l1);
        $this->child($document, $address, 'AdresL2', $settings->seller_address_l2);

        if (filled($settings->seller_phone)) {
            $contact = $this->child($document, $seller, 'DaneKontaktowe');
            $this->child($document, $contact, 'Telefon', $settings->seller_phone);
        }
    }

    private function buyer(DOMDocument $document, DOMElement $root, $tenant): void
    {
        $buyer = $this->child($document, $root, 'Podmiot2');

        $identity = $this->child($document, $buyer, 'DaneIdentyfikacyjne');
        $this->child($document, $identity, 'NIP', $this->digits($tenant->nip));
        $this->child($document, $identity, 'Nazwa', $tenant->name);

        $address = $this->child($document, $buyer, 'Adres');
        $this->child($document, $address, 'KodKraju', 'PL');
        $this->child($document, $address, 'AdresL1', $tenant->street ?: '—');
        $this->child($document, $address, 'AdresL2', trim(($tenant->zip ?? '').' '.($tenant->city ?? '')) ?: '—');

        // Nabywca nie jest jednostką samorządu ani grupą VAT.
        $this->child($document, $buyer, 'JST', '2');
        $this->child($document, $buyer, 'GV', '2');
    }

    private function invoice(DOMDocument $document, DOMElement $root, Invoice $invoice, InvoiceSetting $settings): void
    {
        $fa = $this->child($document, $root, 'Fa');

        $this->child($document, $fa, 'KodWaluty', 'PLN');
        $this->child($document, $fa, 'P_1', $invoice->issued_on->toDateString());

        if (filled($settings->issue_place)) {
            $this->child($document, $fa, 'P_1M', $settings->issue_place);
        }

        $this->child($document, $fa, 'P_2', $invoice->number);
        $this->child($document, $fa, 'P_6', $invoice->sold_on->toDateString());
        $this->child($document, $fa, 'P_13_1', $this->amount($invoice->total_net));
        $this->child($document, $fa, 'P_14_1', $this->amount($invoice->total_vat));
        $this->child($document, $fa, 'P_15', $this->amount($invoice->total_gross));

        $this->annotations($document, $fa);

        $this->child($document, $fa, 'RodzajFaktury', 'VAT');

        foreach ($invoice->lines as $line) {
            $row = $this->child($document, $fa, 'FaWiersz');
            $this->child($document, $row, 'NrWierszaFa', (string) $line->position);
            $this->child($document, $row, 'P_7', $line->name);
            $this->child($document, $row, 'P_8A', $line->unit);
            $this->child($document, $row, 'P_8B', rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.'));
            $this->child($document, $row, 'P_9A', $this->amount($line->unit_price_net));
            $this->child($document, $row, 'P_11', $this->amount($line->net));
            $this->child($document, $row, 'P_12', (string) (int) $line->vat_rate);
        }

        $payment = $this->child($document, $fa, 'Platnosc');
        $term = $this->child($document, $payment, 'TerminPlatnosci');
        $this->child($document, $term, 'Termin', $invoice->due_on->toDateString());
        $this->child($document, $payment, 'FormaPlatnosci', self::PAYMENT_TRANSFER);

        if (filled($settings->bank_account)) {
            $account = $this->child($document, $payment, 'RachunekBankowy');
            $this->child($document, $account, 'NrRB', $settings->bank_account);

            if (filled($settings->bank_swift)) {
                $this->child($document, $account, 'SWIFT', $settings->bank_swift);
            }
        }
    }

    /** Adnotacje obowiązkowe w FA(3): same odpowiedzi „nie dotyczy". */
    private function annotations(DOMDocument $document, DOMElement $fa): void
    {
        $annotations = $this->child($document, $fa, 'Adnotacje');

        foreach (['P_16', 'P_17', 'P_18', 'P_18A'] as $field) {
            $this->child($document, $annotations, $field, '2');
        }

        $exemption = $this->child($document, $annotations, 'Zwolnienie');
        $this->child($document, $exemption, 'P_19N', '1');

        $vehicles = $this->child($document, $annotations, 'NoweSrodkiTransportu');
        $this->child($document, $vehicles, 'P_22N', '1');

        $this->child($document, $annotations, 'P_23', '2');

        $margin = $this->child($document, $annotations, 'PMarzy');
        $this->child($document, $margin, 'P_PMarzyN', '1');
    }

    private function child(DOMDocument $document, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $value === null
            ? $document->createElement($name)
            : $document->createElement($name, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));

        $parent->appendChild($element);

        return $element;
    }

    /** Kwoty bez zbędnych zer — tak jak w dokumentach z aplikacji podatnika. */
    private function amount(float|string|null $value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }
}
