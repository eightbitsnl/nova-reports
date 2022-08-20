<?php

namespace Eightbitsnl\NovaReports\Http\Controllers;

use App\Http\Controllers\Controller;
use Eightbitsnl\NovaReports\Models\Report;

class ReportController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  \App\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function show(Report $report)
    {
        // check count
        if ($report->getCount() > config("nova-reports.webview.max_count", 10)) {
            throw new \Exception("This report has more than " . config("nova-reports.webview.max_count") . " rows. Please use the export action to download the report.");
        }

        return view("NovaReports::reports.webview", [
            "title" => $report->title,
            "items" => $report->getRows(),
        ]);
    }
}
