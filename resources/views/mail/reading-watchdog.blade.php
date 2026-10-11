<x-mail::message>
# Kontrola odczytów — {{ $day->format('d.m.Y') }}

@if ($reported === 0)
Dziś nie wpłynął **żaden odczyt**. Czynnych liczników jest {{ $expected }}.
Zwykle znaczy to, że kolektor nie zapisał danych albo stracił połączenie z bazą.
@else
Wpłynęły odczyty z **{{ $reported }} z {{ $expected }}** czynnych liczników.
Brakuje {{ $missing->count() }}:
@endif

@if ($missing->isNotEmpty())
<x-mail::table>
| Licznik | Numer seryjny | Ostatni odczyt |
|:--------|:--------------|:---------------|
@foreach ($missing as $meter)
| {{ $meter->name }} | {{ $meter->serial_number ?: '—' }} | {{ $lastSeen[$meter->id] ?? 'nigdy' }} |
@endforeach
</x-mail::table>
@endif

@if ($orphans > 0)
Dodatkowo {{ $orphans }} dzisiejszych odczytów nie dopasowało się do żadnego licznika
po numerze seryjnym — sprawdź, czy numery w bazie zgadzają się z tymi, które wysyła kolektor.
@endif

<x-mail::button :url="route('readings.index')">
Otwórz listę odczytów
</x-mail::button>

Wiadomość wygenerowana automatycznie przez {{ config('app.name') }}.
</x-mail::message>
