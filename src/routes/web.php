<?php

use Eightbitsnl\NovaReports\Http\Controllers\ReportController;
use Eightbitsnl\NovaReports\Http\Middleware\AuthorizeWebviews;

Route::middleware( config('nova.middleware') )->group(function () {

	Route::get('/{report:uuid}', [ReportController::class, 'show'])
		->middleware([AuthorizeWebviews::class, 'can:view,report'])
		->name('report.webview');

});