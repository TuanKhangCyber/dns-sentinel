<?php

namespace App\Http\Controllers;

use App\Models\ReconHistory;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly FeatureAccessService $features, private readonly CreditService $credits) {}

    public function json(Request $request, ReconHistory $history): Response
    {
        $this->authorizeHistory($request, $history);
        $this->features->authorize($request->user(), 'export_json');
        $payload = [
            'company' => config('recon.company_name'),
            'generated_at' => now()->toIso8601String(),
            'report' => $history->toArray(),
        ];

        return response()->streamDownload(
            fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            'dns-recon-'.$history->target.'-'.$history->id.'.json',
            ['Content-Type' => 'application/json; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function pdf(Request $request, ReconHistory $history): Response
    {
        $this->authorizeHistory($request, $history);
        $access = $this->features->authorize($request->user(), 'export_pdf');
        $this->credits->charge($request->user(), 'export_pdf', $access['cost'], ReconHistory::class, $history->id);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('reports.recon', [
            'history' => $history,
            'company' => config('recon.company_name'),
            'generatedAt' => now(),
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $pdf->getCanvas()->page_text(36, 810, config('recon.company_name').' | '.__('ui.page').' {PAGE_NUM}/{PAGE_COUNT}', null, 8, [0.35, 0.4, 0.5]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dns-recon-'.$history->target.'-'.$history->id.'.pdf"',
        ]);
    }

    private function authorizeHistory(Request $request, ReconHistory $history): void
    {
        abort_unless($history->user_id === $request->user()->id, 404);
    }
}
