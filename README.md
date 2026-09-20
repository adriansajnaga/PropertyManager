# PM Property Manager

Aplikacja do zarządzania nieruchomościami (Sajnaga) — nieruchomości, lokale, najemcy, liczniki,
odczyty mediów, koszty utrzymania, rachunki oraz miesięczne rozliczenia najemców z generowaniem PDF.

Ten katalog zawiera **kod aplikacji**, a nie gotowy projekt Laravela. Pliki wgrywasz na świeżą
instalację Laravela według instrukcji poniżej.

---

## 1. Wymagania

- PHP 8.2+ (XAMPP ma 8.2.12 — wystarczy)
- Composer ([getcomposer.org](https://getcomposer.org/download/) → `Composer-Setup.exe`)
- Node.js 20+ (masz 22.19) i npm
- MySQL / MariaDB (XAMPP)

## 2. Instalacja

Nowy projekt z Livewire Starter Kit (logowanie, rejestracja, Tailwind) w **osobnym** katalogu:

```bash
cd C:\xampp\htdocs
composer create-project laravel/livewire-starter-kit pm-app
cd pm-app
composer require barryvdh/laravel-dompdf
```

> Composer sam dobierze wersję zgodną z Twoim PHP 8.2. Jeśli mimo to zgłosi błąd wersji PHP,
> zaktualizuj PHP w XAMPP.

## 3. Wgranie kodu aplikacji

Skopiuj zawartość tego katalogu do katalogu `pm-app`, zachowując strukturę folderów:

| Katalog | Zawartość |
|---|---|
| `app/Enums` | typy liczników, mediów, statusy rozliczeń |
| `app/Models` | 14 modeli Eloquent |
| `app/Services` | synchronizacja odczytów + kalkulatory rozliczeń |
| `app/Http/Controllers` | 10 kontrolerów |
| `app/Http/Requests` | walidacja formularzy |
| `app/Console/Commands` | `readings:sync` |
| `config/pm.php` | VAT, nazwa licznika kotłowni, ustawienia synchronizacji |
| `database/migrations` | 14 migracji |
| `database/seeders/DemoSeeder.php` | dane demonstracyjne |
| `resources/views` | widoki Blade + szablon PDF |
| `routes/pm.php`, `routes/console.php` | trasy i harmonogram |
| `tests/Feature` | testy logiki rozliczeń |

Plik `routes/console.php` nadpisuje domyślny — to celowe (zawiera harmonogram synchronizacji).

Na końcu `routes/web.php` dopisz:

```php
require __DIR__.'/pm.php';
```

## 4. Konfiguracja

W `.env`:

```dotenv
APP_LOCALE=pl
APP_TIMEZONE=Europe/Warsaw

DB_CONNECTION=mysql
DB_DATABASE=pm_property_manager
DB_USERNAME=root
DB_PASSWORD=

# Baza źródłowa z odczytami liczników (tylko do odczytu)
IASCOMM_DB_HOST=127.0.0.1
IASCOMM_DB_DATABASE=iascomm_reader
IASCOMM_DB_USERNAME=iascomm_ro
IASCOMM_DB_PASSWORD=
```

W `config/database.php`, w tablicy `connections`, dodaj połączenie źródłowe:

```php
'iascomm' => [
    'driver' => 'mysql',
    'host' => env('IASCOMM_DB_HOST', '127.0.0.1'),
    'port' => env('IASCOMM_DB_PORT', '3306'),
    'database' => env('IASCOMM_DB_DATABASE', 'iascomm_reader'),
    'username' => env('IASCOMM_DB_USERNAME', 'forge'),
    'password' => env('IASCOMM_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'strict' => false,
],
```

**Kolektor nie musi znać `meter_id`** — pisze tylko numer seryjny (kolumna `METER`), tak jak dotąd.
Tłumaczeniem numeru seryjnego na licznik w aplikacji zajmuje się `readings:sync`, dopasowując po
`serial_number` w obrębie tego samego medium (rodzaj wynika z tabeli źródłowej). Odczyt o nieznanym
numerze trafia na listę „Nieprzypisane" i zostaje automatycznie podpięty, gdy dodasz licznik o tym
numerze albo poprawisz literówkę w numerze istniejącego. Każdy przebieg synchronizacji też próbuje
dopiąć zaległe odczyty, więc działa to również wtedy, gdy kolektor pisze wprost do tabeli `readings`
(wypełniając `source_table`, `source_id` i `source_meter_serial`, a `meter_id` zostawiając pustym).

Gdy kolektor zacznie pisać do bazy aplikacji, ustaw `PM_SYNC_CONNECTION=mysql` w `.env` — synchronizacja
będzie czytać z tego samego połączenia i osobne `iascomm` przestanie być potrzebne.

## 5. Uruchomienie

```bash
php artisan migrate
php artisan db:seed --class=DemoSeeder
npm install && npm run build
php artisan serve
```

Aplikacja: `http://127.0.0.1:8000/overview` (po zalogowaniu; konto załóż przez `/register`).

Synchronizacja odczytów ręcznie:

```bash
php artisan readings:sync
```

Na hostingu współdzielonym (cPanel/DirectAdmin) wystarczy jeden cron co minutę:

```
* * * * * cd /sciezka/do/pm-app && php artisan schedule:run >> /dev/null 2>&1
```

## 6. Jak liczone są rozliczenia

**Cena za 1 GJ (moduł „Koszty ciepła")**

```
cena_GJ = (cena_kWh_z_faktury × zużycie_podlicznika_kotłowni) ÷ suma_GJ_wszystkich_liczników_ciepła
```

Kotłownia ma własny podlicznik prądu — aplikacja rozpoznaje go po nazwie `KOTŁOWNIA`
(do zmiany w `config/pm.php`), a nie po fladze „licznik główny", żeby nie pomylić go
z licznikiem budynkowym. Cena kWh pochodzi z jedynego miesięcznego rachunku za prąd —
z tego samego, którym wyceniane jest zużycie prądu w lokalach.

**Rozliczenie najemcy (moduł „Rozliczenia")**

- zużycie mediów = odczyt z 1. dnia kolejnego miesiąca − odczyt z 1. dnia miesiąca rozliczanego
  (brany jest ostatni odczyt nie późniejszy niż dana granica, więc odczyt nie musi trafić co do dnia),
- woda i prąd wyceniane z rachunków (moduł „Rachunki"), ciepło po cenie za GJ. Rachunek można wybrać
  ręcznie z dowolnego miesiąca (faktury bywają za 2–4 miesiące). Domyślnie brany jest rachunek z danego
  miesiąca, a gdy go brak — ostatni wcześniejszy, z ostrzeżeniem. Wybór zapisuje się w rozliczeniu,
  więc „Przelicz ponownie" używa tych samych rachunków,
- koszty utrzymania: `roczny_koszt ÷ powierzchnia_nieruchomości ÷ 12 × powierzchnia_lokalu`,
- VAT 23% doliczany na końcu od sumy netto (stawkę zmienisz w `config/pm.php`).

Dane wejściowe każdej pozycji (odczyty, daty, ceny, numery faktur) są zapisywane w wierszach
rozliczenia. Po zatwierdzeniu (status `Zatwierdzone`) rozliczenie jest niezmienne — późniejsze
korekty odczytów czy cen nie zmieniają już wystawionego dokumentu.

Dane demo (Budynek A, luty 2026): podlicznik kotłowni zużył 500 kWh po 0,85 zł, liczniki ciepła
łącznie 100 GJ → **4,25 zł/GJ**. Rozliczenie Lokalu 12 zamyka się kwotą **475,00 zł netto**
(584,25 zł brutto). Żeby to zobaczyć, utwórz najpierw rozliczenie kosztów ciepła za luty 2026
(faktura `FV/E/02/2026`), a potem rozliczenie tego lokalu.

> Przykład w `pm-property-manager-design.md` zakładał dwie różne ceny prądu (0,75 zł dla kotłowni
> i 0,85 zł dla lokali) i dawał 465,00 zł. Przy jednym rachunku za prąd obie pozycje liczone są
> tą samą ceną — stąd różnica.

## 7. Testy

```bash
php artisan test
```

Testy pokrywają: wyliczenie ceny za GJ, pełne rozliczenie najemcy z dokumentacji, blokadę zmian
po zatwierdzeniu, ostrzeżenia przy brakujących cenach oraz idempotentność synchronizacji odczytów
(łącznie z odczytami osieroconymi i zachowaniem kursora po błędzie).

## 8. Czego jeszcze nie ma

- rozliczenie kosztów ciepła jest jedno na miesiąc (bez podziału na wiele kotłowni),
- zmiana najemcy w trakcie miesiąca: rozliczenie trafia do najemcy z pierwszego dnia miesiąca
  (historia przypisań jest zapisywana, ale zużycie nie jest jeszcze dzielone proporcjonalnie),
- brak portalu najemcy — dostęp mają tylko pracownicy biura, wszyscy z równymi uprawnieniami,
- PDF jest generowany na żądanie i nie jest zapisywany na dysku (kolumna `pdf_path` czeka na tę funkcję).
