<?php

use Eightbitsnl\NovaReports\Http\Controllers\ReportController;

// Route::get('/{report:uuid}', [ReportController::class, 'show'])->name('report.webview');
Route::get('/{report:uuid}', [ReportController::class, 'show'])->name('report.webview');