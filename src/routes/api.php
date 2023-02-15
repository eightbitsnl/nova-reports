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

Route::post("preview/{report?}", function (Request $request, Report $report = null) {
    return response()->json(
        (new Report([
            "entrypoint" => request()->input("entrypoint"),
            "relations" => request()->input("relations"),
            "query" => request()->input("query"),
        ]))->preview()
    );
});