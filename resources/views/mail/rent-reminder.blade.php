<x-mail::message>
# Przypomnienie o płatności czynszu

@foreach (preg_split('/\R{2,}/', trim($body)) as $paragraph)
{!! nl2br(e($paragraph)) !!}

@endforeach

**Zaległe płatności**

| Miesiąc | Lokal | Faktura | Termin | Kwota |
| :------ | :---- | :------ | :----- | ----: |
@foreach ($charges as $charge)
| {{ $charge->month->isoFormat('MMMM YYYY') }} | {{ $charge->unit->description }} | {{ $charge->invoice_number ?: '—' }} | {{ $charge->due_on?->format('d.m.Y') ?? '—' }} | {{ \App\Support\Format::money($charge->amount) }} zł |
@endforeach
| | | | **Razem** | **{{ \App\Support\Format::money($total) }} zł** |

{{ config('pm.report_issuer') }}

*Wiadomość wygenerowana automatycznie.*
</x-mail::message>
