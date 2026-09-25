@php
    use App\Support\Format;

    $money = fn ($value) => Format::money($value);
    $tenant = $invoice->tenant;
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Faktura {{ $invoice->number }}</title>
    <style>
        @page { margin: 12mm 10mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; line-height: 1.35; }
        h1 { font-size: 13pt; margin: 0 0 1mm; }
        h2 { font-size: 8pt; margin: 4mm 0 1.5mm; text-transform: uppercase; color: #333; border-bottom: .5pt solid #999; padding-bottom: .5mm; }
        table { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: .5pt solid #999; padding: 1mm 1.5mm; }
        table.data th { background: #ececec; text-align: left; font-size: 7pt; }
        table.plain td { padding: .5mm 0; vertical-align: top; }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .muted { color: #666; }
        .xs { font-size: 7pt; }
        .b { font-weight: bold; }
        .head { border: .5pt solid #111; padding: 2.5mm; margin-bottom: 3mm; }
        .box { border: .5pt solid #999; padding: 2mm; }
        .sum td { border: .5pt solid #111; padding: 1.2mm 1.5mm; }
        .grand { background: #ececec; font-weight: bold; }
        .qr { text-align: center; margin-top: 4mm; }
        .bottom { position: fixed; bottom: 0; left: 0; right: 0; font-size: 6.5pt; color: #666; }
    </style>
</head>
<body>

<div class="head">
    <table class="plain">
        <tr>
            <td style="width: 60%">
                <h1>Faktura {{ $invoice->number }}</h1>
                <span class="xs muted">Faktura podstawowa · {{ $invoice->status->label() }}</span>
            </td>
            <td style="width: 40%" class="num">
                @if ($invoice->ksef_number)
                    <span class="muted xs">Numer KSeF</span><br>
                    <span class="b xs">{{ $invoice->ksef_number }}</span>
                @else
                    <span class="muted xs">Dokument nie został jeszcze wysłany do KSeF</span>
                @endif
            </td>
        </tr>
    </table>
</div>

<table class="plain">
    <tr>
        <td style="width: 50%; padding-right: 3mm">
            <div class="box">
                <span class="muted xs">Sprzedawca</span><br>
                <span class="b">{{ $settings->seller_name }}</span><br>
                <span class="xs">{{ $settings->seller_address_l1 }}</span><br>
                <span class="xs">{{ $settings->seller_address_l2 }}</span><br>
                <span class="xs">NIP {{ $settings->seller_nip }}</span>
                @if ($settings->seller_phone)
                    <br><span class="xs">tel. {{ $settings->seller_phone }}</span>
                @endif
            </div>
        </td>
        <td style="width: 50%">
            <div class="box">
                <span class="muted xs">Nabywca</span><br>
                <span class="b">{{ $tenant?->name ?? '—' }}</span><br>
                <span class="xs">{{ $tenant?->street }}</span><br>
                <span class="xs">{{ trim(($tenant?->zip ?? '').' '.($tenant?->city ?? '')) }}</span><br>
                <span class="xs">NIP {{ $tenant?->nip ?: '—' }}</span>
            </div>
        </td>
    </tr>
</table>

<h2>Szczegóły</h2>

<table class="plain xs">
    <tr>
        <td style="width: 34%">Data wystawienia: <span class="b">{{ $invoice->issued_on->format('d.m.Y') }}</span></td>
        <td style="width: 33%">Data sprzedaży: <span class="b">{{ $invoice->sold_on->format('d.m.Y') }}</span></td>
        <td style="width: 33%">Miejsce wystawienia: <span class="b">{{ $settings->issue_place ?: '—' }}</span></td>
    </tr>
</table>

<h2>Pozycje</h2>

<table class="data">
    <thead>
        <tr>
            <th style="width: 6%" class="ctr">Lp.</th>
            <th>Nazwa towaru lub usługi</th>
            <th style="width: 12%" class="ctr">Ilość</th>
            <th style="width: 15%" class="ctr">Cena netto</th>
            <th style="width: 10%" class="ctr">Stawka</th>
            <th style="width: 15%" class="ctr">Wartość netto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td class="ctr">{{ $line->position }}</td>
                <td>{{ $line->name }}</td>
                <td class="ctr">{{ rtrim(rtrim(number_format((float) $line->quantity, 4, ',', ' '), '0'), ',') }} {{ $line->unit }}</td>
                <td class="num">{{ $money($line->unit_price_net) }}</td>
                <td class="ctr">{{ (int) $line->vat_rate }}%</td>
                <td class="num">{{ $money($line->net) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>Podsumowanie stawek podatku</h2>

<table class="data">
    <thead>
        <tr>
            <th style="width: 40%" class="ctr">Stawka podatku</th>
            <th class="ctr">Kwota netto</th>
            <th class="ctr">Kwota podatku</th>
            <th class="ctr">Kwota brutto</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="ctr">{{ (int) $invoice->vat_rate }}%</td>
            <td class="num">{{ $money($invoice->total_net) }}</td>
            <td class="num">{{ $money($invoice->total_vat) }}</td>
            <td class="num">{{ $money($invoice->total_gross) }}</td>
        </tr>
    </tbody>
</table>

<table class="sum" style="margin-top: 2mm">
    <tr>
        <td style="width: 70%" class="muted">Kwota należności ogółem</td>
        <td class="num grand">{{ $money($invoice->total_gross) }} PLN</td>
    </tr>
</table>

<h2>Płatność</h2>

<table class="plain xs">
    <tr>
        <td style="width: 34%">Forma płatności: <span class="b">Przelew</span></td>
        <td style="width: 33%">Termin płatności: <span class="b">{{ $invoice->due_on->format('d.m.Y') }}</span></td>
        <td style="width: 33%">Waluta: <span class="b">PLN</span></td>
    </tr>
    @if ($settings->bank_account)
        <tr>
            <td colspan="3">
                Numer rachunku: <span class="b">{{ $settings->bank_account }}</span>
                @if ($settings->bank_swift)
                    · SWIFT: <span class="b">{{ $settings->bank_swift }}</span>
                @endif
            </td>
        </tr>
    @endif
</table>

@if ($qrUrl)
    <div class="qr">
        <h2 style="text-align: left">Weryfikacja faktury w KSeF</h2>
        <img src="{{ $qrCode }}" alt="Kod QR weryfikujący fakturę" style="width: 32mm; height: 32mm">
        <div class="b xs">{{ $invoice->ksef_number ?: 'OFFLINE' }}</div>
        <div class="xs muted" style="margin-top: 1mm">
            Zeskanuj kod albo otwórz link, aby sprawdzić, czy faktura znajduje się w KSeF:
        </div>
        <div class="xs" style="word-break: break-all">{{ $qrUrl }}</div>
    </div>
@endif

<div class="bottom">
    <table class="plain">
        <tr>
            <td>{{ config('pm.report_issuer') }}</td>
            <td class="num">Wygenerowano {{ now()->format('d.m.Y H:i') }}</td>
        </tr>
    </table>
</div>

</body>
</html>
