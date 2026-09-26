@php
    use App\Support\Format;

    $money = fn ($value) => Format::money($value).' zł';
    $date = fn ($value) => $value?->format('Y.m.d');
    $tenant = $invoice->tenant;
    $isReceipt = $invoice->isReceipt();
    $logo = $settings->logoDataUri();

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
        @page { margin: 12mm 11mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; line-height: 1.35; }
        h1 { font-size: 15pt; margin: 0; letter-spacing: .4pt; }
        table { width: 100%; border-collapse: collapse; }
        table.plain td { padding: .4mm 0; vertical-align: top; }
        table.grid th, table.grid td { border: .5pt solid #999; padding: .7mm 1.5mm; }
        table.grid th { background: #f1f1f1; font-size: 7pt; text-align: left; font-weight: bold; }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .muted { color: #666; }
        .xs { font-size: 7.5pt; }
        .b { font-weight: bold; }
        .party { border: .5pt solid #999; padding: 2.5mm; }
        .party .label { font-size: 7.5pt; color: #666; }
        .totals td { border: .5pt solid #111; padding: 1.4mm 1.8mm; }
        .totals .grand { background: #f1f1f1; font-weight: bold; font-size: 10pt; }
        .ksef { border: .5pt solid #999; padding: 2mm; }
        /* Podpisy i stopka w jednym bloku przy dolnej krawędzi pierwszej strony. */
        .page-bottom { position: absolute; bottom: 3mm; left: 0; right: 0; }
        /* Stopka z danymi wystawcy zostaje na pierwszej stronie. */
        .doc-footer { margin-top: 3mm; text-align: center; font-size: 6.5pt; color: #666; border-top: .5pt solid #ddd; padding-top: 1.2mm; }
        .page-number { position: fixed; bottom: -13mm; right: 0; font-size: 7pt; color: #444; }
        .page-number:after { content: counter(page) " z {{ $qrUrl ? 2 : 1 }}"; }
        /* Strona weryfikacyjna odwzorowuje wizualizację z KSeF. */
        .verify { page-break-before: always; padding-top: 0; }
        .verify .box { width: 118mm; }
        .verify h2 { font-size: 11pt; margin: 0 0 3.5mm; font-weight: normal; }
        .verify .hint { font-size: 7pt; color: #333; margin: 0 0 1.5mm; }
        .verify .link { font-size: 7pt; word-break: break-all; color: #1d4ed8; }
        .verify .ksef-number { font-size: 8.5pt; margin-top: 3mm; }
        .verify .made-in { font-size: 7pt; color: #333; margin-top: 1.5mm; }
        .verify .environment { text-align: center; font-size: 8pt; color: #777; margin-top: 55mm; }
    </style>
</head>
<body>

<table class="plain">
    <tr>
        <td style="width: 58%">
            @if ($logo)
                <img src="{{ $logo }}" alt="" style="max-height: 14mm; max-width: 55mm; margin-bottom: 2mm">
            @endif
            <h1>{{ $isReceipt ? 'RACHUNEK' : 'FAKTURA VAT' }} {{ $invoice->number }}</h1>
            @if ($invoice->ksef_number)
                <div class="xs" style="margin-top: 1.5mm">
                    <span class="muted">Numer KSeF:</span> <span class="b">{{ $invoice->ksef_number }}</span><br>
                    <span class="muted">Data nadania numeru KSeF:</span>
                    <span class="b">{{ ($invoice->ksef_sent_at ?? $invoice->issued_on)->format('d.m.Y') }}</span>
                </div>
            @endif
        </td>
        <td style="width: 42%">
            <table class="plain xs">
                <tr><td class="muted">Data wystawienia:</td><td class="num b">{{ $date($invoice->issued_on) }}</td></tr>
                <tr><td class="muted">Data sprzedaży:</td><td class="num b">{{ $date($invoice->sold_on) }}</td></tr>
                <tr><td class="muted">Numer {{ $isReceipt ? 'rachunku' : 'faktury' }}:</td><td class="num b">{{ $invoice->number }}</td></tr>
                @if ($settings->issue_place)
                    <tr><td class="muted">Miejsce wystawienia:</td><td class="num b">{{ $settings->issue_place }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="plain" style="margin-top: 3mm">
    <tr>
        <td style="width: 50%; padding-right: 2mm">
            <div class="party" style="height: 26mm">
                <span class="label">{{ $isReceipt ? 'Wystawca:' : 'Sprzedawca:' }}</span><br>
                <span class="b">{{ $issuer['name'] }}</span><br>
                <span class="xs">{{ $issuer['l2'] }}</span><br>
                <span class="xs">{{ $issuer['l1'] }}</span><br>
                <span class="xs">{{ $issuer['id'] }}</span>
            </div>
        </td>
        <td style="width: 50%">
            <div class="party" style="height: 26mm">
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
            <th style="width: 18%">Forma płatności</th>
            <th style="width: 16%">Termin</th>
            <th>Płatność na konto</th>
            <th style="width: 20%" class="num">Kwota przelewu</th>
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
            @if ($isReceipt && $settings->receipt_note)
                <span class="xs muted">{{ $settings->receipt_note }}</span>
            @elseif (! $isReceipt && ! $invoice->ksef_number)
                <span class="xs muted">Dokument nie został jeszcze wysłany do KSeF.</span>
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

<div class="page-bottom">
    <div class="doc-footer">
        {{ $issuer['name'] }}@if (! $isReceipt && $settings->seller_nip) · NIP: {{ $settings->seller_nip }}@endif
        @if (! $isReceipt && $settings->seller_regon) · REGON: {{ $settings->seller_regon }}@endif
        · wystawiono w {{ config('app.name') }}
    </div>
</div>

@if ($qrUrl)
    <div class="verify">
        <div class="box">
            <h2>Sprawdź, czy Twoja faktura znajduje się w KSeF!</h2>

            <table class="plain">
                <tr>
                    <td style="width: 42mm; vertical-align: top">
                        <img src="{{ $qrCode }}" alt="Kod QR weryfikujący fakturę" style="width: 40mm; height: 40mm">
                    </td>
                    <td style="vertical-align: top; padding-left: 3mm">
                        <p class="hint">
                            Nie możesz zeskanować kodu z obrazka? Kliknij w link weryfikacyjny
                            i przejdź do weryfikacji faktury!
                        </p>
                        <div class="link">{{ $qrUrl }}</div>
                    </td>
                </tr>
            </table>

            <div class="ksef-number b">{{ $invoice->ksef_number }}</div>

            <div class="made-in">Wytworzona w: {{ config('app.name') }}</div>
        </div>

        @if ($environmentNote ?? null)
            <div class="environment">{{ $environmentNote }}</div>
        @endif
    </div>
@endif

<div class="page-number"></div>

</body>
</html>
