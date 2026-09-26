<x-mail::message>
# {{ $invoice->title() }}

@foreach (preg_split('/\R{2,}/', trim($body)) as $paragraph)
{!! nl2br(e($paragraph)) !!}

@endforeach

**Szczegóły dokumentu**

- {{ $invoice->document_type->title() }}: {{ $invoice->number }}
- Data wystawienia: {{ $invoice->issued_on->format('d.m.Y') }}
- Termin płatności: {{ $invoice->due_on->format('d.m.Y') }}
- Do zapłaty: {{ \App\Support\Format::money($invoice->total_gross) }} zł
@if ($invoice->ksef_number)
- Numer KSeF: {{ $invoice->ksef_number }}
@endif

{{ config('pm.report_issuer') }}
</x-mail::message>
