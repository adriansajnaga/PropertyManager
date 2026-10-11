<?php

namespace App\Http\Controllers;

use App\Mail\ReadingWatchdogMail;
use App\Models\MailSetting;
use App\Models\Meter;
use App\Models\User;
use App\Services\ModuleReadingConverter;
use App\Services\ReadingSyncService;
use App\Services\ReadingWatchdog;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Kontrola odczytów wywoływana z crona przez podpisany adres. Gdy wszystkie czynne
 * liczniki przysłały dziś stan — nie dzieje się nic. Gdy nie — idzie e-mail z listą
 * brakujących. Odpowiedź jest tekstowa, żeby było ją widać w logu zadania.
 */
class ReadingWatchdogController extends Controller
{
    public function __invoke(
        ReadingWatchdog $watchdog,
        ReadingSyncService $sync,
        ModuleReadingConverter $modules,
    ): Response {
        // Najpierw to samo, co robi wejście na listę odczytów: dowiązanie wpisów
        // kolektora po numerze seryjnym i przeliczenie nakładek. Bez tego kontrola
        // zgłaszałaby braki, które są tylko nieprzypisanymi odczytami.
        $sync->linkOrphans();
        $modules->convertAll();

        $report = $watchdog->report();
        $day = $report['day']->format('Y-m-d');

        if ($report['missing']->isEmpty()) {
            return $this->text(sprintf(
                "OK %s: %d z %d liczników przysłało odczyt.\n",
                $day,
                $report['reported'],
                $report['expected'],
            ));
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            return $this->text(sprintf(
                "BRAKI %s: %d z %d liczników bez odczytu, ale nie ma komu wysłać wiadomości.\n",
                $day,
                $report['missing']->count(),
                $report['expected'],
            ));
        }

        if (! $this->shouldNotify($report['day']->format('Y-m-d'), $report['missing']->pluck('id')->all())) {
            return $this->text(sprintf(
                "BRAKI %s: %d z %d liczników bez odczytu, wiadomość już poszła.\n",
                $day,
                $report['missing']->count(),
                $report['expected'],
            ));
        }

        Mail::to($recipients)->send(new ReadingWatchdogMail(
            day: $report['day'],
            missing: $report['missing'],
            expected: $report['expected'],
            reported: $report['reported'],
            orphans: $report['orphans'],
            lastSeen: $this->lastSeen($watchdog, $report['missing']),
        ));

        return $this->text(sprintf(
            "BRAKI %s: %d z %d liczników bez odczytu, wysłano na %s.\n",
            $day,
            $report['missing']->count(),
            $report['expected'],
            implode(', ', $recipients),
        ));
    }

    /**
     * Cron bywa ustawiony co godzinę, a ta sama lista braków nie musi przychodzić
     * kilka razy dziennie. Powiadamiamy, gdy lista się zmieni — czyli gdy braków
     * przybędzie albo ubędzie.
     *
     * @param  array<int, int>  $missingIds
     */
    private function shouldNotify(string $day, array $missingIds): bool
    {
        $key = 'readings-watchdog.'.$day;
        $signature = md5(implode(',', $missingIds));

        if (Cache::get($key) === $signature) {
            return false;
        }

        Cache::put($key, $signature, now()->endOfDay());

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function recipients(): array
    {
        $admins = User::where('is_admin', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        return $admins !== [] ? $admins : array_filter([MailSetting::current()->from_address]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Meter>  $missing
     * @return array<int, string>
     */
    private function lastSeen(ReadingWatchdog $watchdog, $missing): array
    {
        return $missing
            ->mapWithKeys(fn (Meter $meter) => [
                $meter->id => $watchdog->lastReadingAt($meter)?->format('d.m.Y, H:i') ?? 'nigdy',
            ])
            ->all();
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
