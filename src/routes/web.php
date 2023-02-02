<?php

use Eightbitsnl\NovaReports\Http\Controllers\ReportController;
use Eightbitsnl\NovaReports\Http\Middleware\AuthorizeWebviews;
use Eightbitsnl\NovaReports\Http\Middleware\Authorize;

Route::get("webview/{report:uuid}", [ReportController::class, "webview"])
    ->middleware([AuthorizeWebviews::class, "can:view,report"])
    ->name("nova-reports.webview");

Route::get("download/{report:uuid}", [ReportController::class, "download"])
    ->middleware(["can:view,report"])
    ->name("nova-reports.download");

Route::get("signeddownload/{encrypted}", [ReportController::class, "download_signed"])
    ->name("nova-reports.download_signed");