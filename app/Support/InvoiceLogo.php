<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Logo faktury trzymamy w formacie, który dompdf wstawia bez rozszerzenia GD — w JPEG.
 * Wgrany plik (PNG, GIF, WEBP) przerabiamy raz, przy zapisie ustawień, bo tam GD
 * wystarczy mieć jednorazowo; sam wydruk PDF działa potem na każdym serwerze.
 */
class InvoiceLogo
{
    /** Największa krawędź zapisywanego logo — na fakturze zajmuje ok. 55 mm. */
    private const MAX_WIDTH = 900;

    public const ACCEPTED = 'png,jpg,jpeg,gif,webp';

    public static function store(UploadedFile $file): string
    {
        $jpeg = self::toJpeg($file);

        if ($jpeg !== null) {
            $path = 'invoice-logo/'.bin2hex(random_bytes(8)).'.jpg';
            Storage::disk('local')->put($path, $jpeg);

            return $path;
        }

        // Bez GD zapisujemy plik bez zmian; na wydruku pojawi się, gdy serwer ma GD.
        return $file->store('invoice-logo', 'local');
    }

    /** Czy serwer potrafi przerobić logo na format bezpieczny dla PDF. */
    public static function canConvert(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /** Czy dompdf wstawi do PDF plik o takim typie. */
    public static function renderable(string $mime): bool
    {
        return $mime === 'image/jpeg' || function_exists('imagecreatefrompng');
    }

    private static function toJpeg(UploadedFile $file): ?string
    {
        if (! self::canConvert()) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_WIDTH / max(1, $width));

        $target = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));

        // JPEG nie zna przezroczystości — podkładamy biel, czyli tło faktury.
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled(
            $target, $source,
            0, 0, 0, 0,
            imagesx($target), imagesy($target), $width, $height,
        );

        ob_start();
        imagejpeg($target, null, 92);
        $jpeg = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        return $jpeg;
    }
}
