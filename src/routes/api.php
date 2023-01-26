<?php

use Eightbitsnl\NovaReports\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Route::get("init/{report?}", function (Request $request, Report $report = null) {
    // prepare output
    // --------------------------------------------------
    $result = [];

    // generate a list of selectable entrypoints
    // --------------------------------------------------
    $result["entrypoints"] = Report::getReportables()->mapWithKeys(function ($class) {
        $data = [
            "value" => $class,
            "label" => Str::plural(Str::title(Str::snake(class_basename($class), " "))),
            "rules" => (new $class())->getReportRules(),
            "available_relations" => $class::getReportableRelations(),
            "exportable_fields" => $class::getExportableFields(),
        ];

        return [$class => $data];
    });

    // selected entrypoint
    // --------------------------------------------------
    // $result['entrypoint'] = (!is_null($report) && $report->entrypoint) ? $report->entrypoint : $result['entrypoints']->first()['value'];

    // response
    // --------------------------------------------------
    return response()->json($result);
});

Route::post("preview/{report?}", function (Request $request, Report $report = null) {
    return response()->json(
        (new Report([
            "entrypoint" => request()->input("entrypoint"),
            "relations" => request()->input("relations"),
            "query" => request()->input("query"),
        ]))->preview()
    );
});

Route::get("/download/{report?}", function (Request $request, Report $report = null) {
    $latest = $report->getLatestExportFile(); /** @todo move to \Eightbitsnl\NovaReports\Exports */

    if (empty($latest)) {
        abort(404);
    }

    return Storage::disk(config('nova-reports.filesystem'))->download($latest, "report-" . $report->id . "-" . basename($latest));
});
