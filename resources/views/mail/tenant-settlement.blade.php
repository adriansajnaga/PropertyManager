<x-mail::message>
# Rozliczenie {{ $settlement->month->isoFormat('MMMM YYYY') }}

@foreach (preg_split('/\R{2,}/', trim($body)) as $paragraph)
{!! nl2br(e($paragraph)) !!}

@endforeach

**Szczegóły dokumentu**

- Lokal: {{ $settlement->unit->description }} ({{ $settlement->unit->property->name }}, {{ $settlement->unit->property->address }})
- Najemca: {{ $settlement->tenant?->name ?? '—' }}
- Numer dokumentu: {{ $settlement->number }}
- Razem netto: {{ \App\Support\Format::money($settlement->total_net) }} zł
- Razem brutto: {{ \App\Support\Format::money($settlement->total_gross) }} zł

{{ config('pm.report_issuer') }}
</x-mail::message>
