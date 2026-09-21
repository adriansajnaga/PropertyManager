<?php

namespace App\Http\Controllers;

use App\Mail\RentReminderMail;
use App\Models\Tenant;
use App\Services\RentPaymentSummary;
use App\Services\Sms\SmsGateway;
use App\Support\PhoneNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class TenantPaymentController extends Controller
{
    public function __construct(private readonly RentPaymentSummary $summary) {}

    public function pdf(Request $request, Tenant $tenant)
    {
        $year = (int) $request->query('year', now()->year);

        return Pdf::loadView('pdf.tenant-payments', [
            'tenant' => $tenant,
            'summary' => $this->summary->forTenant($tenant, $year),
            'year' => $year,
        ])->setPaper('a4')->download($this->filename($tenant, $year));
    }

    /**
     * Przypomnienie e-mailem, SMS-em albo obiema drogami naraz. Kanały są od siebie
     * niezależne: awaria jednego nie blokuje drugiego, a wynik każdego widać osobno.
     */
    public function reminder(Request $request, Tenant $tenant, SmsGateway $sms)
    {
        $charges = $this->summary->outstandingFor($tenant);

        if ($charges->isEmpty()) {
            return back()->withErrors(['channels' => 'Ten najemca nie ma zaległych płatności.']);
        }

        $channels = (array) $request->input('channels', []);
        $wantsEmail = in_array('email', $channels, true);
        $wantsSms = in_array('sms', $channels, true);

        $data = $request->validate([
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,sms'],
            'email' => [Rule::requiredIf($wantsEmail), 'nullable', 'email'],
            'subject' => [Rule::requiredIf($wantsEmail), 'nullable', 'string', 'max:255'],
            'body' => [Rule::requiredIf($wantsEmail), 'nullable', 'string', 'max:5000'],
            'phone' => [Rule::requiredIf($wantsSms), 'nullable', 'string', 'max:32', function ($attribute, $value, $fail) {
                if (filled($value) && PhoneNumber::normalize($value) === null) {
                    $fail('Numer telefonu jest nieprawidłowy.');
                }
            }],
            'sms_message' => [Rule::requiredIf($wantsSms), 'nullable', 'string', 'max:612'],
        ], [
            'channels.required' => 'Zaznacz, czy wysłać e-mail, SMS, czy oba.',
        ], [
            'email' => 'adres e-mail',
            'subject' => 'temat',
            'body' => 'treść wiadomości',
            'phone' => 'numer telefonu',
            'sms_message' => 'treść SMS',
        ]);

        $sent = [];
        $failed = [];

        if ($wantsEmail) {
            try {
                Mail::to($data['email'])->send(new RentReminderMail(
                    $tenant,
                    $charges,
                    $data['body'],
                    $data['subject'],
                ));
                $sent[] = 'e-mail na '.$data['email'];
            } catch (Throwable $e) {
                report($e);
                $failed['email'] = 'E-mail nie został wysłany: '.$e->getMessage();
            }
        }

        if ($wantsSms) {
            $phone = PhoneNumber::normalize($data['phone']);

            try {
                $sms->send($phone, $data['sms_message']);
                $sent[] = 'SMS na '.PhoneNumber::format($phone);
            } catch (Throwable $e) {
                report($e);
                $failed['phone'] = 'SMS nie został wysłany: '.$e->getMessage();
            }
        }

        $redirect = back();

        if ($sent !== []) {
            $redirect = $redirect->with('status', 'Przypomnienie wysłane: '.implode(' oraz ', $sent).'.');
        }

        if ($failed !== []) {
            // Po częściowym sukcesie formularz wraca zaznaczony tylko na kanałach, które
            // zawiodły — żeby ponowne kliknięcie nie wysłało drugi raz tego, co już doszło.
            $retry = array_values(array_filter([
                isset($failed['email']) ? 'email' : null,
                isset($failed['phone']) ? 'sms' : null,
            ]));

            $redirect = $redirect
                ->withErrors($failed)
                ->withInput(['channels' => $retry] + $request->except('channels', '_token'));
        }

        return $redirect;
    }

    private function filename(Tenant $tenant, int $year): string
    {
        return sprintf('platnosci-%d-%s.pdf', $year, str($tenant->name)->slug());
    }
}
