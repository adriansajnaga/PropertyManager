<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ustawienia serwera poczty trzymane w bazie, żeby dało się je zmienić
 * z poziomu aplikacji — na hostingu współdzielonym edycja .env bywa niewygodna.
 */
class MailSetting extends Model
{
    protected $fillable = [
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::first() ?? new static;
    }

    public function isConfigured(): bool
    {
        return filled($this->host) && filled($this->from_address);
    }

    /** Nadpisuje konfigurację poczty na czas bieżącego żądania. */
    public function apply(): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $this->host,
            'mail.mailers.smtp.port' => $this->port ?? 587,
            // SSL na 465 to połączenie szyfrowane od pierwszego bajtu (smtps),
            // TLS na 587 to zwykłe smtp z podniesieniem szyfrowania przez STARTTLS.
            'mail.mailers.smtp.scheme' => $this->encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.encryption' => $this->encryption,
            'mail.mailers.smtp.username' => $this->username,
            'mail.mailers.smtp.password' => $this->password,
            'mail.from.address' => $this->from_address,
            'mail.from.name' => $this->from_name ?: config('pm.report_issuer'),
        ]);
    }
}
