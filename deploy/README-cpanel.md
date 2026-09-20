# Wdrożenie na ascomm.pl/PM (cPanel, bez terminala i Composera)

Aplikacja stoi pod adresem `https://ascomm.pl/PM`.

Układ katalogów na serwerze:

```
/home/UŻYTKOWNIK/
├── PropertyManager/        ← repozytorium z GitHuba (kod, vendor, storage, .env)
└── public_html/PM/         ← to, co widzi przeglądarka (tylko public/ + index.php)
```

Kod aplikacji i `.env` leżą **poza** `public_html`, więc nie da się ich otworzyć
z przeglądarki. `.cpanel.yml` przy każdym wdrożeniu kopiuje zawartość `public/`
do `public_html/PM` i podmienia `index.php` na wersję z `deploy/index.php`.

Ponieważ na hostingu nie ma Composera, katalog `vendor/` jest trzymany
w repozytorium — po aktualizacji pakietów trzeba go zacommitować razem z kodem.

---

## 1. Baza danych (cPanel → MySQL® Databases)

1. Utwórz bazę, np. `ascomm_pm`.
2. Utwórz użytkownika, np. `ascomm_pm` z mocnym hasłem.
3. Dodaj użytkownika do bazy z uprawnieniami **ALL PRIVILEGES**.
4. Zapisz trzy wartości: nazwa bazy, użytkownik, hasło (pełne nazwy z przedrostkiem konta).

## 2. Import danych (cPanel → phpMyAdmin)

1. Wybierz utworzoną bazę z listy po lewej.
2. Zakładka **Import** → wskaż plik `pm-baza.sql` przygotowany lokalnie → **Wykonaj**.

Plik zawiera strukturę i dane z komputera, więc konta, lokale, liczniki,
odczyty i rozliczenia są od razu na serwerze.

## 3. Pobranie kodu (cPanel → Git™ Version Control)

1. **Create** → zaznacz **Clone a Repository**.
2. `Clone URL`: `https://github.com/adriansajnaga/PropertyManager.git`
   (repozytorium prywatne → w miejscu hasła podaj **Personal Access Token** z GitHuba).
3. `Repository Path`: `/home/UŻYTKOWNIK/PropertyManager` — **nie** w `public_html`.
4. **Create**. Pierwsze klonowanie chwilę trwa (repozytorium waży ~90 MB przez `vendor/`).

## 4. Plik .env (cPanel → File Manager)

1. Wejdź do `/home/UŻYTKOWNIK/PropertyManager`.
2. Włącz pokazywanie plików ukrytych (Settings → Show Hidden Files).
3. Wgraj przygotowany lokalnie plik `.env` **albo** utwórz go ręcznie na wzór
   `deploy/env.production.example` i uzupełnij dane bazy z punktu 1.

## 5. Wdrożenie plików publicznych

W **Git™ Version Control** przy repozytorium: zakładka **Pull or Deploy**
→ **Deploy HEAD Commit**. cPanel wykona `.cpanel.yml` i utworzy `public_html/PM`.

Sprawdź, czy działa: `https://ascomm.pl/PM`

## 6. Uprawnienia (tylko jeśli pojawi się błąd zapisu)

W File Managerze ustaw uprawnienia **755** rekurencyjnie na katalogach:

- `PropertyManager/storage`
- `PropertyManager/bootstrap/cache`

---

## Kolejne aktualizacje aplikacji

1. Na komputerze: **Commit** i **Push** w GitHub Desktop.
2. W cPanelu: **Git Version Control → Pull or Deploy → Update from Remote**,
   a następnie **Deploy HEAD Commit**.

Jeżeli aktualizacja zawiera nowe migracje bazy, trzeba je wykonać osobno —
bez terminala najprościej wyeksportować zmiany struktury z komputera
i zaimportować w phpMyAdmin.

## Czego NIE ma w repozytorium

- `.env` (hasła do bazy i poczty),
- `storage/app/private/` (skany umów i inne pliki lokali),
- `node_modules/` (potrzebne tylko do budowania CSS na komputerze).
