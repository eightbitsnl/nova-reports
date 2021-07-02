<?php

namespace Eightbitsnl\NovaReports\Http\Controllers;

use App\Http\Controllers\Controller;
use Eightbitsnl\NovaReports\Models\Report;

class ReportController extends Controller {

	/**
     * Display the specified resource.
     *
     * @param  \App\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function show(Report $report)
    {
		$items = $report->getResults();

        return view('NovaReports::reports.webview', [
            'title' => $report->title,
            'items' => $items
        ]);
        // return response()->json($items);
    }
}