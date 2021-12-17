<?php

use Eightbitsnl\NovaReports\Http\Controllers\ReportController;
use Eightbitsnl\NovaReports\Http\Middleware\Authorize;

Route::middleware(array_merge(config('nova.middleware'), [Authorize::class]))->group(function () {

	Route::get('/{report:uuid}', [ReportController::class, 'show'])
		->middleware('can:view,report')
		->name('report.webview');

});