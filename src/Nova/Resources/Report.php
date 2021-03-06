<?php

namespace Eightbitsnl\NovaReports\Nova\Resources;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Eightbitsnl\NovaReports\Actions\Export\Excel;
use Eightbitsnl\NovaReports\Nova\Fields\QuerybuilderField;
use Laravel\Nova\Fields\File;

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
    public static $title = 'title';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'title',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')
                ->sortable(),

			Text::make('Title')
				->rules('required'),

            $this->when( config('nova-reports.webview.enabled') && $this->uuid,
                Text::make('Webview', function(){
                    return '<a class="no-underline dim text-primary font-bold" href="'. route('report.webview', ['report'=>$this->uuid]) .'" target="_blank">View</a>';
                })->asHtml()
            ),

            File::make('Template', 'templatefile')
                ->disk('local')
                ->acceptedTypes('.xlsx')
                ->help('Optional Excel file template. First sheet will be filled with report data'),

			Textarea::make('Note')
				->nullable()
				->alwaysShow(),

			QuerybuilderField::make('Query', 'reportfields'),

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
        return [
			new Excel
		];
    }
}
