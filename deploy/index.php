<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Front controller dla hostingu współdzielonego
|--------------------------------------------------------------------------
|
| Ten plik leży w public_html/pm, a sama aplikacja — poza katalogiem
| publicznym. Poniższa ścieżka wskazuje katalog, do którego cPanel klonuje
| repozytorium. Domyślnie: /home/UŻYTKOWNIK/PropertyManager
|
| Jeżeli sklonujesz repozytorium gdzie indziej, popraw tylko tę jedną linię.
|
*/

$appBase = dirname(__DIR__, 2).'/PropertyManager';

if (! is_file($appBase.'/vendor/autoload.php')) {
    http_response_code(500);
    exit('Nie znaleziono aplikacji w katalogu: '.$appBase.' — popraw ścieżkę $appBase w index.php.');
}

// Tryb przerwy technicznej...
if (file_exists($maintenance = $appBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appBase.'/vendor/autoload.php';

(require_once $appBase.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
