@php
    use App\Support\Format;

    $money = fn ($value) => Format::money($value).' zł';
    $date = fn ($value) => $value?->format('Y.m.d');
    $tenant = $invoice->tenant;
    $isReceipt = $invoice->isReceipt();

    // Rachunek wystawia osoba fizyczna, faktura — firma.
    $issuer = $isReceipt
        ? ['name' => $settings->receipt_issuer_name, 'l1' => $settings->receipt_address_l1, 'l2' => $settings->receipt_address_l2, 'id' => $settings->receipt_identifier]
        : ['name' => $settings->seller_name, 'l1' => $settings->seller_address_l1, 'l2' => $settings->seller_address_l2, 'id' => 'NIP: '.$settings->seller_nip];
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->title() }}</title>
    <style>
        @page { margin: 12mm 11mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; line-height: 1.35; }
        h1 { font-size: 15pt; margin: 0; letter-spacing: .4pt; }
        table { width: 100%; border-collapse: collapse; }
        table.plain td { padding: .4mm 0; vertical-align: top; }
        table.grid th, table.grid td { border: .5pt solid #999; padding: 1.2mm 1.5mm; }
        table.grid th { background: #f1f1f1; font-size: 7.5pt; text-align: left; font-weight: bold; }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .muted { color: #666; }
        .xs { font-size: 7.5pt; }
        .b { font-weight: bold; }
        .party { border: .5pt solid #999; padding: 2mm; height: 22mm; }
        .party .label { font-size: 7.5pt; color: #666; }
        .totals td { border: .5pt solid #111; padding: 1.4mm 1.8mm; }
        .totals .grand { background: #f1f1f1; font-weight: bold; font-size: 10pt; }
        .sign { margin-top: 12mm; font-size: 7pt; color: #444; }
        .sign td { text-align: center; padding-top: 10mm; }
        .sign .line { border-top: .5pt dotted #777; margin-bottom: 1mm; }
        .ksef { border: .5pt solid #999; padding: 2mm; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 6.5pt; color: #666; border-top: .5pt solid #ddd; padding-top: 1mm; }
    </style>
</head>
<body>

<table class="plain">
    <tr>
        <td style="width: 58%; padding-top: 2mm">
            <h1>{{ $isReceipt ? 'RACHUNEK' : 'FAKTURA VAT' }} {{ $invoice->number }}</h1>
            <span class="xs muted">
                {{ $invoice->document_type->title() }} za czynsz
                @if ($settings->issue_place) · {{ $settings->issue_place }} @endif
            </span>
        </td>
        <td style="width: 42%">
            <table class="plain xs">
                <tr><td class="muted">Data wystawienia:</td><td class="num b">{{ $date($invoice->issued_on) }}</td></tr>
                <tr><td class="muted">Data sprzedaży:</td><td class="num b">{{ $date($invoice->sold_on) }}</td></tr>
                <tr><td class="muted">Numer {{ $isReceipt ? 'rachunku' : 'faktury' }}:</td><td class="num b">{{ $invoice->number }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="plain" style="margin-top: 3mm">
    <tr>
        <td style="width: 50%; padding-right: 2mm">
            <div class="party">
                <span class="label">{{ $isReceipt ? 'Wystawca:' : 'Sprzedawca:' }}</span><br>
                <span class="b">{{ $issuer['name'] }}</span><br>
                <span class="xs">{{ $issuer['l2'] }}</span><br>
                <span class="xs">{{ $issuer['l1'] }}</span><br>
                <span class="xs">{{ $issuer['id'] }}</span>
            </div>
        </td>
        <td style="width: 50%">
            <div class="party">
                <span class="label">Nabywca:</span><br>
                <span class="b">{{ $tenant?->name ?? '—' }}</span><br>
                <span class="xs">{{ trim(($tenant?->street ?? '').', '.trim(($tenant?->zip ?? '').' '.($tenant?->city ?? '')), ', ') }}</span><br>
                @if ($tenant?->nip)
                    <span class="xs">NIP: {{ $tenant->nip }}</span>
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="grid" style="margin-top: 3mm">
    <thead>
        <tr>
            <th style="width: 20%">Forma płatności</th>
            <th style="width: 18%">Termin</th>
            <th>Płatność na konto</th>
            <th style="width: 20%" class="num">Kwota do zapłaty</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Przelew</td>
            <td>{{ $date($invoice->due_on) }}</td>
            <td class="xs">
                @if ($settings->bank_account)
                    @if ($settings->bank_swift){{ $settings->bank_swift }} — @endif{{ $settings->bank_account }}
                @else
                    —
                @endif
            </td>
            <td class="num b">{{ $money($invoice->total_gross) }}</td>
        </tr>
    </tbody>
</table>

<table class="grid" style="margin-top: 3mm">
    <thead>
        <tr>
            <th style="width: 7%" class="ctr">L.p.</th>
            <th>Nazwa towaru / usługi</th>
            <th style="width: 10%" class="ctr">Ilość</th>
            <th style="width: 9%" class="ctr">J.m.</th>
            <th style="width: 15%" class="ctr">Cena netto</th>
            @unless ($isReceipt)
                <th style="width: 8%" class="ctr">VAT</th>
            @endunless
            <th style="width: 16%" class="ctr">Wartość netto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td class="ctr">{{ $line->position }}</td>
                <td>{{ $line->name }}</td>
                <td class="ctr">{{ rtrim(rtrim(number_format((float) $line->quantity, 4, ',', ' '), '0'), ',') }}</td>
                <td class="ctr">{{ $line->unit }}</td>
                <td class="num">{{ $money($line->unit_price_net) }}</td>
                @unless ($isReceipt)
                    <td class="ctr">{{ (int) $line->vat_rate }}%</td>
                @endunless
                <td class="num">{{ $money($line->net) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="plain" style="margin-top: 3mm">
    <tr>
        <td style="width: 52%; vertical-align: bottom">
            @if ($qrUrl)
                <div class="ksef">
                    <table class="plain">
                        <tr>
                            <td style="width: 26mm">
                                <img src="{{ $qrCode }}" alt="Kod QR KSeF" style="width: 24mm; height: 24mm">
                            </td>
                            <td class="xs">
                                <span class="muted">Faktura w Krajowym Systemie e-Faktur</span><br>
                                <span class="b">{{ $invoice->ksef_number }}</span><br>
                                <span class="muted" style="word-break: break-all">{{ $qrUrl }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
            @elseif (! $isReceipt)
                <span class="xs muted">Dokument nie został jeszcze wysłany do KSeF.</span>
            @elseif ($settings->receipt_note)
                <span class="xs muted">{{ $settings->receipt_note }}</span>
            @endif
        </td>
        <td style="width: 48%">
            <table class="totals">
                <tr>
                    <td class="muted">Razem netto</td>
                    <td class="num">{{ $money($invoice->total_net) }}</td>
                </tr>
                @unless ($isReceipt)
                    <tr>
                        <td class="muted">Razem VAT {{ (int) $invoice->vat_rate }}%</td>
                        <td class="num">{{ $money($invoice->total_vat) }}</td>
                    </tr>
                @endunless
                <tr>
                    <td class="grand">Do zapłaty</td>
                    <td class="num grand">{{ $money($invoice->total_gross) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="sign">
    <tr>
        <td style="width: 45%">
            <div class="line"></div>
            Podpis osoby upoważnionej do wystawienia<br>{{ $isReceipt ? 'rachunku' : 'faktury' }} — {{ $issuer['name'] }}
        </td>
        <td style="width: 10%"></td>
        <td style="width: 45%">
            <div class="line"></div>
            Podpis osoby upoważnionej do odbioru
        </td>
    </tr>
</table>

<div class="footer">
    {{ $issuer['name'] }}@if (! $isReceipt && $settings->seller_nip) · NIP: {{ $settings->seller_nip }}@endif
    @if (! $isReceipt && $settings->seller_regon) · REGON: {{ $settings->seller_regon }}@endif
    · dokument wystawiony w {{ config('app.name') }}
</div>

</body>
</html>
