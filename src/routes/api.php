<?php

use Eightbitsnl\NovaReports\Http\Requests\ReportPreviewRequest;
use Eightbitsnl\NovaReports\Http\Requests\ReportWebPreviewRequest;
use Eightbitsnl\NovaReports\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Route::get("init/{report?}", function (Request $request, Report $report = null) {
    // prepare output
    // --------------------------------------------------
    $result = [];

    $result["entrypoint"] = optional($report)->entrypoint;
    $result["loadrelation"] = optional($report)->loadrelation;
    $result["query"] = optional($report)->query;
    $result["export_fields"] = optional($report)->export_fields ?: [];

    // generate a list of selectable entrypoints
    // --------------------------------------------------
    $result["entrypoints"] = Report::getEntrypoints()->mapWithKeys(function ($class) {
        $data = [
            "value" => $class,
            "label" => Str::plural(Str::title(Str::snake(class_basename($class), " "))),
            "rules" => (new $class())->getReportRules(),
            "available_relations" => $class::getReportableRelations(),
            "exportable_fields" => $class::getExportableFields(),
        ];

        return [$class => $data];
    });

    // response
    // --------------------------------------------------
    return response()->json($result);
});

Route::post("preview/{report?}", function (ReportPreviewRequest $request, Report $report = null) {

    $validated = $request->validated();

    return response()->json(
        (new Report([
            "entrypoint" => $validated["entrypoint"],
            "relations" => $validated["relations"],
            "query" => $validated["query"],
            // "grouping_option" => $validated["grouping_option"],
        ]))->preview()
    );
});

Route::post("webpreview/{report?}", function (ReportWebPreviewRequest $request, Report $report = null) {

    $validated = $request->validated();

    return response()->json(
        (new Report([
            "entrypoint" => $validated["entrypoint"],
            "export_fields" => $validated["export_fields"],
            "relations" => $validated["loadrelation"],
            "query" => $validated["query"],
            // "grouping_option" => $validated["grouping_option"],
        ]))->webpreview()
    );
});
