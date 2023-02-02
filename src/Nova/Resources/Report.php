<?php

namespace Eightbitsnl\NovaReports\Nova\Resources;

use Exception;
use ReflectionClass;
use App\Nova\Resource;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Eightbitsnl\NovaReports\Actions\Export\Excel;
use Eightbitsnl\NovaReports\Models\Report as ModelsReport;
use Eightbitsnl\NovaReports\Nova\Fields\QuerybuilderField;
use Laravel\Nova\Fields\Select;

class Report extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \Eightbitsnl\NovaReports\Models\Report::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = "title";

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = ["title"];

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($action = config("nova-reports.index_query")) {
            $invokable = (new ReflectionClass($action))->newInstance();

            return $invokable($request, $query);
        }

        return $query;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__("ID"), "id")->sortable(),

            Text::make("Title")->rules("required"),

            Text::make("Count", function () {
                return $this->resource->getCount();
            }),

            $this->when(
                config("nova-reports.webview.enabled") && $this->uuid,
                Text::make("Webview", function () {
                    return '<a class="no-underline dim text-primary font-bold" href="' . route("nova-reports.webview", ["report" => $this->uuid]) . '" target="_blank">View</a>';
                })->asHtml()
            ),

            File::make("Template", "templatefile")
                ->disk(config('nova-reports.filesystem'))
                ->path('reports/templates')
                ->acceptedTypes(".xlsx")
                ->help("Optional Excel file template. First sheet will be filled with report data"),

            Textarea::make("Note")
                ->nullable()
                ->alwaysShow(),

            QuerybuilderField::make("Query", "reportfields"),

            Select::make("Group", "grouping_option")
                ->options([
                    ModelsReport::OUTPUT_TYPE_CROSSJOIN => "Crossjoin",
                    ModelsReport::OUTPUT_TYPE_FLAT => "Flat",
                ])
                ->default(ModelsReport::OUTPUT_TYPE_CROSSJOIN)
                ->displayUsingLabels()
                ->hideFromIndex(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [new Excel()];
    }
}
