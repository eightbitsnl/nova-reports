<?php

namespace Eightbitsnl\NovaReports\Http\Controllers;

use App\Http\Controllers\Controller;
use Eightbitsnl\NovaReports\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    /**
     * Webview
     *
     * @param  \Eightbitsnl\NovaReports\Models\Report  $report
     * @return \Illuminate\Http\Response
     */
    public function webview(Request $request, Report $report)
    {
        // check count
        if ($report->getCount() > config("nova-reports.webview.max_count", 10)) {
            throw new \Exception("This report has more than " . config("nova-reports.webview.max_count") . " rows. Please use the export action to download the report.");
        }

        return view("NovaReports::reports.webview", [
            "title" => $report->title,
            "headings" => $report->getHeadings(),
            "items" => $report->getRows(),
        ]);
    }

    /**
     * Download
     *
     * @param  \Eightbitsnl\NovaReports\Models\Report  $report
     * @return \Illuminate\Http\Response
     */
    public function download(Report $report)
    {
        $latest = $report->getLatestExportFile();

        if (empty($latest)) {
            abort(404);
        }

        return Storage::disk(config('nova-reports.filesystem'))->download($latest, "report-" . $report->id . "-" . basename($latest));
    }

    /**
     * Download Signed
     *
     * @param  string  $encrypted data
     * @return \Illuminate\Http\Response
     */
    public function download_signed(Request $request, string $encrypted)
    {
        // Validate signature
        if (! $request->hasValidSignature()) {
            abort(401);
        }

        $decrypted = json_decode(Crypt::decryptString($encrypted));

        // Validate User
        if( $decrypted->u !== auth()->user()->id )
        {
            abort(401);
        }

        return Storage::disk(config('nova-reports.filesystem'))->download($decrypted->p);
    }

}
