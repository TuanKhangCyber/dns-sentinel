<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Services\Scanner\ScanReportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ScanReportController extends Controller
{
    public function __construct(
        private readonly ScanReportService $reports,
        private readonly FeatureAccessService $features,
        private readonly CreditService $credits,
    ) {}

    public function json(Scan $scan): Response
    {
        Gate::authorize('view', $scan);
        $this->features->authorize(request()->user(), 'export_json');
        $report = $this->reports->payload($scan);

        return response()->streamDownload(
            fn () => print json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            $this->filename($scan, 'json'),
            ['Content-Type' => 'application/json; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function csv(Scan $scan): Response
    {
        Gate::authorize('view', $scan);
        $this->features->authorize(request()->user(), 'export_csv');
        $contents = $this->reports->csv($this->reports->payload($scan));

        return response()->streamDownload(
            fn () => print $contents,
            $this->filename($scan, 'csv'),
            ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function pdf(Scan $scan): Response
    {
        Gate::authorize('view', $scan);
        $access = $this->features->authorize(request()->user(), 'export_pdf');
        $this->credits->charge(request()->user(), 'export_pdf', $access['cost'], Scan::class, $scan->id);
        $report = $this->reports->payload($scan);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('reports.scanner', ['report' => $report])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $pdf->getCanvas()->page_text(36, 812, config('scanner.company_name').' | Page {PAGE_NUM}/{PAGE_COUNT}', null, 8, [0.35, 0.4, 0.5]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($scan, 'pdf').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filename(Scan $scan, string $extension): string
    {
        $target = preg_replace('/[^A-Za-z0-9._-]+/', '-', $scan->target) ?: 'target';

        return "security-scan-{$target}-{$scan->id}.{$extension}";
    }
}
