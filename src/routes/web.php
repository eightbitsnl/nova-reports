<?php

use Eightbitsnl\NovaReports\Http\Controllers\ReportController;

Route::middleware( config('nova.middleware') )->group(function () {

	Route::get('/{report:uuid}', [ReportController::class, 'show'])
		->middleware('can:view,report')
		->name('report.webview');

});