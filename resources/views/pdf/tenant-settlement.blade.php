@php
    use App\Enums\SettlementLineCategory;
    use App\Support\Format;

    $lines = $settlement->lines;
    $costLines = $lines->where('category', SettlementLineCategory::MaintenanceCost);
    $mediaLines = $lines->where('category', '!=', SettlementLineCategory::MaintenanceCost);

    $unit = $settlement->unit;
    $property = $unit->property;
    $periodStart = $settlement->month->copy();
    $periodEnd = $settlement->month->copy()->addMonth();

    $money = fn ($value, $decimals = 2) => Format::money($value, $decimals);
    $line = fn (SettlementLineCategory $category) => $mediaLines->firstWhere('category', $category);

    $annualCosts = $maintenanceCosts->sum('annual_cost');
    $monthlyPerM2 = (float) $property->total_area > 0 ? $annualCosts / (float) $property->total_area / 12 : 0;
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Rozliczenie {{ $settlement->number }}</title>
    <style>
        @page { margin: 10mm 9mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7pt; color: #111; line-height: 1.25; }
        h1 { font-size: 10.5pt; margin: 0; text-align: center; text-transform: uppercase; letter-spacing: .3pt; }
        h2 { font-size: 7.5pt; margin: 3mm 0 1mm; text-transform: uppercase; color: #333; border-bottom: .5pt solid #999; padding-bottom: .5mm; }
        .sub { text-align: center; font-size: 7pt; color: #444; }
        .head { border: .5pt solid #111; padding: 2mm; margin-bottom: 2.5mm; }
        table { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: .5pt solid #999; padding: .8mm 1.2mm; }
        table.data th { background: #ececec; text-align: left; font-size: 6.5pt; font-weight: bold; }
        table.plain td { padding: .4mm 0; vertical-align: top; }
        .num { text-align: right; }
        .nowrap { white-space: nowrap; }
        .muted { color: #666; }
        .xs { font-size: 6pt; }
        .b { font-weight: bold; }
        .totals td { border: .5pt solid #111; padding: 1.2mm; font-size: 8pt; }
        .totals .grand { background: #ececec; font-weight: bold; font-size: 9pt; }
        .foot { font-size: 6pt; color: #666; }
        .sign td { padding-top: 8mm; font-size: 6pt; color: #555; text-align: center; }
        .sign .lineb { border-top: .5pt solid #999; width: 45%; }
        /* Podpisy i stopka zawsze przy dolnej krawędzi kartki. */
        .bottom { position: fixed; bottom: 0; left: 0; right: 0; }
    </style>
</head>
<body>

<h1>Rozliczenie mediów i kosztów eksploatacyjnych</h1>
<div class="sub">
    lokal w budynku usługowym przy {{ $property->address }} ·
    okres rozliczeniowy od {{ $periodStart->format('d.m.Y') }} do {{ $periodEnd->format('d.m.Y') }}
</div>

<div class="head">
    <table class="plain">
        <tr>
            <td style="width: 38%">
                <span class="muted xs">Użytkownik lokalu</span><br>
                <span class="b">{{ $settlement->tenant?->name ?? '—' }}</span><br>
                <span class="xs">{{ $settlement->tenant?->street }}, {{ $settlement->tenant?->zip }} {{ $settlement->tenant?->city }}</span>
                @if ($settlement->tenant?->nip)
                    <span class="xs">· NIP {{ $settlement->tenant->nip }}</span>
                @endif
            </td>
            <td style="width: 34%">
                <span class="muted xs">Lokal</span><br>
                <span class="b">{{ $unit->description }}</span><br>
                <span class="xs">Powierzchnia lokalu: {{ $money($unit->area) }} m² · budynek {{ $money($property->total_area) }} m²</span>
            </td>
            <td style="width: 28%" class="num">
                <span class="muted xs">Dokument</span><br>
                <span class="b">{{ $settlement->number }}</span><br>
                <span class="xs">Data wykonania rozliczenia: {{ optional($settlement->generated_at)->format('d.m.Y') ?? now()->format('d.m.Y') }}</span>
            </td>
        </tr>
    </table>
</div>

<h2>Wyliczenie cen jednostkowych mediów</h2>
<table class="data">
    <thead>
        <tr>
            <th style="width: 22%">Rodzaj</th>
            <th style="width: 28%">Numer dokumentu</th>
            <th class="num">Łączne zużycie</th>
            <th class="num">Kwota faktury netto</th>
            <th class="num">Koszt jednostkowy</th>
        </tr>
    </thead>
    <tbody>
        @foreach ([['Energia elektryczna', $settlement->electricBill, 'kWh'], ['Woda i kanalizacja', $settlement->waterBill, 'm³']] as [$label, $bill, $unitLabel])
            <tr>
                <td>{{ $label }}</td>
                <td>{{ $bill?->invoice_number ?? '—' }}</td>
                <td class="num">{{ $bill?->consumption_value !== null ? Format::reading($bill->consumption_value, $unitLabel, true) : '—' }}</td>
                <td class="num">{{ $bill?->net_amount !== null ? $money($bill->net_amount).' zł' : '—' }}</td>
                <td class="num b">{{ $bill ? $money($bill->net_price, 4).' zł/'.$unitLabel : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>Wyliczenie ceny jednostkowej energii cieplnej</h2>
@if ($heatSettlement)
    @php
        $boilerCost = (float) $heatSettlement->boiler_kwh_consumed * (float) ($heatSettlement->electricBill?->net_price ?? 0);
        $heatSerials = $heatSettlement->lines->pluck('meter_serial')->filter()->implode(', ');
    @endphp
    <table class="data">
        <tbody>
            <tr>
                <td style="width: 6%" class="num">1.</td>
                <td>Łączne zużycie energii elektrycznej przez pompę ciepła (licznik {{ $heatSettlement->boiler_meter_serial ?? '—' }})</td>
                <td class="num b" style="width: 22%">{{ Format::reading($heatSettlement->boiler_kwh_consumed, 'kWh', true) }}</td>
            </tr>
            <tr>
                <td class="num">2.</td>
                <td>Koszt łącznego zużycia energii przez pompę ciepła netto
                    <span class="muted xs">(pkt 1 × {{ $money($heatSettlement->electricBill?->net_price, 4) }} zł/kWh)</span>
                </td>
                <td class="num b">{{ $money($boilerCost) }} zł</td>
            </tr>
            <tr>
                <td class="num">3.</td>
                <td>Łączne zużycie energii cieplnej <span class="muted xs">(liczniki {{ $heatSerials ?: '—' }})</span></td>
                <td class="num b">{{ Format::reading($heatSettlement->total_gj_consumed, 'GJ', true) }}</td>
            </tr>
            <tr>
                <td class="num">4.</td>
                <td>Koszt 1 GJ energii cieplnej netto <span class="muted xs">(pkt 2 ÷ pkt 3)</span></td>
                <td class="num b">{{ $money($heatSettlement->price_per_gj) }} zł/GJ</td>
            </tr>
        </tbody>
    </table>
@else
    <div class="muted">Poza sezonem grzewczym — kotłownia nie pracowała, koszt energii cieplnej nie występuje.</div>
@endif

<h2>Wyliczenie cen jednostkowych rocznej eksploatacji budynku</h2>
<table class="data">
    <thead>
        <tr>
            <th style="width: 5%">Lp.</th>
            <th style="width: 49%">Rodzaj kosztu</th>
            <th class="num nowrap">Roczny koszt netto</th>
            <th class="num nowrap">Miesięczny koszt netto</th>
            <th class="num nowrap">Koszt jedn. (zł/m²/mies.)</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($maintenanceCosts as $index => $cost)
            <tr>
                <td class="num">{{ $index + 1 }}</td>
                <td>{{ $cost->description }}</td>
                <td class="num">{{ $money($cost->annual_cost) }} zł</td>
                <td class="num">{{ $money((float) $cost->annual_cost / 12) }} zł</td>
                <td class="num">{{ $money((float) $property->total_area > 0 ? (float) $cost->annual_cost / (float) $property->total_area / 12 : 0) }} zł</td>
            </tr>
        @empty
            <tr><td colspan="5">Brak kosztów eksploatacyjnych.</td></tr>
        @endforelse
    </tbody>
    @if ($maintenanceCosts->isNotEmpty())
        <tfoot>
            <tr class="b">
                <td colspan="2">RAZEM</td>
                <td class="num">{{ $money($annualCosts) }} zł</td>
                <td class="num">{{ $money($annualCosts / 12) }} zł</td>
                <td class="num">{{ $money($monthlyPerM2) }} zł</td>
            </tr>
        </tfoot>
    @endif
</table>

<h2>Odczyty stanu liczników</h2>
<table class="data">
    <thead>
        <tr>
            <th style="width: 30%">Typ, nr seryjny</th>
            <th class="num">Odczyt początkowy</th>
            <th class="num">Data odczytu pocz.</th>
            <th class="num">Odczyt końcowy</th>
            <th class="num">Data odczytu końc.</th>
            <th class="num">Δ odczytu</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($mediaLines as $row)
            <tr>
                <td>
                    <span class="b">{{ $row->label }}</span>
                    <span class="xs muted">{{ $row->meter_model ? $row->meter_model.', ' : '' }}{{ $row->meter_serial }}</span>
                </td>
                <td class="num">{{ Format::reading($row->start_state, $row->consumption_unit) }}</td>
                <td class="num">
                    {{ $periodStart->format('d.m.Y') }}
                    @if ($row->start_interpolated)
                        <span class="xs muted">(wyliczony z {{ optional($row->start_date)->format('d.m.Y') }})</span>
                    @endif
                </td>
                <td class="num">{{ Format::reading($row->end_state, $row->consumption_unit) }}</td>
                <td class="num">
                    {{ $periodEnd->format('d.m.Y') }}
                    @if ($row->end_interpolated)
                        <span class="xs muted">(wyliczony z {{ optional($row->end_date)->format('d.m.Y') }})</span>
                    @endif
                </td>
                <td class="num b">{{ Format::reading($row->consumption, $row->consumption_unit, true) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>Rozliczenie kosztów dla lokalu</h2>
<table class="data">
    <thead>
        <tr>
            <th style="width: 34%">Rodzaj kosztu</th>
            <th class="num">Ilość</th>
            <th class="num">Cena netto</th>
            <th class="num">Wartość netto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ([SettlementLineCategory::Electric, SettlementLineCategory::Water, SettlementLineCategory::Heat] as $category)
            @php($row = $line($category))
            @if ($row)
                <tr>
                    <td>{{ $row->label }}</td>
                    <td class="num">{{ Format::reading($row->consumption, $row->consumption_unit, true) }}</td>
                    <td class="num">{{ Format::price($row->unit_price, $row->consumption_unit) }} zł</td>
                    <td class="num b">{{ $money($row->amount) }} zł</td>
                </tr>
            @endif
        @endforeach
        @if ($costLines->isNotEmpty())
            <tr>
                <td>Eksploatacja budynku</td>
                <td class="num">{{ $money($unit->area) }} m²</td>
                <td class="num">{{ $money($monthlyPerM2) }} zł/m²/mies.</td>
                <td class="num b">{{ $money($costLines->sum('amount')) }} zł</td>
            </tr>
        @endif
    </tbody>
</table>

<table class="totals" style="width: 62mm; margin-left: auto; margin-top: 2.5mm;">
    <tr>
        <td>RAZEM NETTO</td>
        <td class="num">{{ $money($settlement->total_net) }} zł</td>
    </tr>
    <tr>
        <td>VAT {{ number_format((float) $settlement->vat_rate * 100, 0) }}%</td>
        <td class="num">{{ $money($settlement->vatAmount()) }} zł</td>
    </tr>
    <tr class="grand">
        <td>RAZEM BRUTTO</td>
        <td class="num">{{ $money($settlement->total_gross) }} zł</td>
    </tr>
</table>

<div class="bottom">
    <table class="sign">
        <tr>
            <td><div class="lineb" style="margin: 0 auto;">Wystawił</div></td>
            <td><div class="lineb" style="margin: 0 auto;">Odebrał</div></td>
        </tr>
    </table>

    <div class="foot">
        Uwagi: brak uwag. Dokument wygenerowany automatycznie przez {{ config('pm.report_issuer') }}
        na podstawie odczytów liczników za okres {{ $periodStart->format('d.m.Y') }} – {{ $periodEnd->format('d.m.Y') }}.
    </div>
</div>

</body>
</html>
