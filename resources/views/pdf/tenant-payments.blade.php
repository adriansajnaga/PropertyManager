@php
    use App\Support\Format;

    $charges = $summary['charges'];
    $byUnit = $summary['byUnit'];
    $units = $charges->pluck('unit')->unique('id')->sortBy('description');
    $money = fn ($value) => Format::money($value);
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Lista faktur i płatności {{ $year }} — {{ $tenant->name }}</title>
    <style>
        @page { margin: 12mm 10mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; line-height: 1.3; }
        h1 { font-size: 12pt; margin: 0; text-align: center; text-transform: uppercase; letter-spacing: .3pt; }
        h2 { font-size: 8.5pt; margin: 4mm 0 1.5mm; text-transform: uppercase; color: #333; border-bottom: .5pt solid #999; padding-bottom: .5mm; }
        .sub { text-align: center; font-size: 8pt; color: #444; margin-bottom: 3mm; }
        .head { border: .5pt solid #111; padding: 2.5mm; margin-bottom: 3mm; }
        table { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: .5pt solid #999; padding: 1mm 1.5mm; }
        table.data th { background: #ececec; text-align: center; font-size: 7pt; font-weight: bold; }
        table.plain td { padding: .5mm 0; vertical-align: top; }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .muted { color: #666; }
        .nowrap { white-space: nowrap; }
        .xs { font-size: 7pt; }
        .b { font-weight: bold; }
        .sum td { border: .5pt solid #111; padding: 1.2mm 1.5mm; }
        .sum .grand { background: #ececec; font-weight: bold; }
        .late { color: #a15c00; }
        .due { color: #a11212; font-weight: bold; }
        .bottom { position: fixed; bottom: 0; left: 0; right: 0; font-size: 6.5pt; color: #666; }
    </style>
</head>
<body>

<h1>Lista faktur i płatności</h1>
<div class="sub">czynsz najmu za rok {{ $year }}</div>

<div class="head">
    <table class="plain">
        <tr>
            <td style="width: 50%">
                <span class="muted xs">Najemca</span><br>
                <span class="b">{{ $tenant->name }}</span><br>
                <span class="xs">{{ $tenant->fullAddress() ?: '—' }}</span>
                @if ($tenant->nip)
                    <span class="xs">· NIP {{ $tenant->nip }}</span>
                @endif
                @if ($tenant->email)
                    <br><span class="xs">{{ $tenant->email }}</span>
                @endif
            </td>
            <td style="width: 28%">
                <span class="muted xs">Lokale</span><br>
                @forelse ($units as $unit)
                    <span class="b xs">{{ $unit->description }}</span><br>
                @empty
                    <span class="xs">—</span>
                @endforelse
            </td>
            <td style="width: 22%" class="num">
                <span class="muted xs">Zestawienie</span><br>
                <span class="b">{{ $year }}</span><br>
                <span class="xs">Data wykonania: {{ now()->format('d.m.Y') }}</span>
            </td>
        </tr>
    </table>
</div>

@forelse ($units as $unit)
    @php($unitCharges = $byUnit[$unit->id] ?? collect())
    @php($unitCharged = $unitCharges->sum(fn ($charge) => (float) $charge->amount))
    @php($unitPaid = $unitCharges->filter->isPaid()->sum(fn ($charge) => (float) $charge->amount))

    <h2>{{ $unit->description }} — {{ $unit->property->name }}</h2>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 6%">Rok</th>
                <th style="width: 15%">Miesiąc</th>
                <th style="width: 19%">Numer faktury</th>
                <th style="width: 15%">Kwota</th>
                <th style="width: 16%">Termin płatności</th>
                <th style="width: 16%">Data płatności</th>
                <th style="width: 13%">Status</th>
            </tr>
        </thead>
        <tbody>
            @for ($month = 1; $month <= 12; $month++)
                @php($charge = $unitCharges->first(fn ($item) => (int) $item->month->month === $month))
                <tr>
                    <td class="ctr">{{ $year }}</td>
                    <td style="text-transform: uppercase">
                        {{ \Carbon\Carbon::create($year, $month, 1)->isoFormat('MMMM') }}
                    </td>
                    <td class="ctr">{{ $charge?->invoice_number ?: '—' }}</td>
                    <td class="ctr nowrap">{{ $charge ? $money($charge->amount).' zł' : '—' }}</td>
                    <td class="ctr">{{ $charge?->due_on?->format('d.m.Y') ?? '—' }}</td>
                    <td @class(['ctr' => true, 'late' => $charge?->isPaid() && $charge->due_on && $charge->paid_on->gt($charge->due_on)])>
                        {{ $charge?->paid_on?->format('d.m.Y') ?? ($charge ? 'brak' : '—') }}
                    </td>
                    <td @class(['ctr' => true, 'due' => (bool) $charge?->isOverdue()])>
                        @if (! $charge)
                            —
                        @elseif ($charge->isOverdue())
                            po terminie
                        @else
                            {{ $charge->status->label() }}
                        @endif
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>

    <table class="sum" style="margin-top: 1.5mm">
        <tr>
            <td style="width: 31%" class="muted xs">Podsumowanie lokalu</td>
            <td style="width: 23%" class="num nowrap">naliczono <span class="b">{{ $money($unitCharged) }} zł</span></td>
            <td style="width: 23%" class="num nowrap">zapłacono <span class="b">{{ $money($unitPaid) }} zł</span></td>
            <td style="width: 23%" class="num nowrap grand">pozostało {{ $money($unitCharged - $unitPaid) }} zł</td>
        </tr>
    </table>
@empty
    <p>Brak naliczeń czynszu w {{ $year }} roku.</p>
@endforelse

@if ($units->count() > 1)
    <h2>Razem</h2>
    <table class="sum">
        <tr>
            <td style="width: 31%" class="muted xs">Wszystkie lokale najemcy</td>
            <td style="width: 23%" class="num nowrap">naliczono <span class="b">{{ $money($summary['charged']) }} zł</span></td>
            <td style="width: 23%" class="num nowrap">zapłacono <span class="b">{{ $money($summary['paid']) }} zł</span></td>
            <td style="width: 23%" class="num nowrap grand">pozostało {{ $money($summary['outstanding']) }} zł</td>
        </tr>
    </table>
@endif

@if ($summary['overdue']->isNotEmpty())
    <p class="xs" style="margin-top: 2mm">
        <span class="due">Zaległości na dzień {{ now()->format('d.m.Y') }}:</span>
        {{ $summary['overdue']->map(fn ($charge) => $charge->month->isoFormat('MMMM YYYY').' ('.$charge->unit->description.')')->implode(', ') }}
        — łącznie {{ $money($summary['overdue']->sum(fn ($charge) => (float) $charge->amount)) }} zł.
    </p>
@endif

<div class="bottom">
    <table class="plain">
        <tr>
            <td>{{ config('pm.report_issuer') }}</td>
            <td class="num">Zestawienie wygenerowane {{ now()->format('d.m.Y H:i') }}</td>
        </tr>
    </table>
</div>

</body>
</html>
