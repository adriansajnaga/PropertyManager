<?php

namespace App\Http\Controllers;

use App\Mail\RentReminderMail;
use App\Models\Tenant;
use App\Services\RentPaymentSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

    public function reminder(Request $request, Tenant $tenant)
    {
        $charges = $this->summary->outstandingFor($tenant);

        if ($charges->isEmpty()) {
            return back()->withErrors(['email' => 'Ten najemca nie ma zaległych płatności.']);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], [
            'email' => 'adres e-mail',
            'subject' => 'temat',
            'body' => 'treść wiadomości',
        ]);

        Mail::to($data['email'])->send(new RentReminderMail(
            $tenant,
            $charges,
            $data['body'],
            $data['subject'],
        ));

        return back()->with('status', "Przypomnienie zostało wysłane na adres {$data['email']}.");
    }

    private function filename(Tenant $tenant, int $year): string
    {
        return sprintf('platnosci-%d-%s.pdf', $year, str($tenant->name)->slug());
    }
}
