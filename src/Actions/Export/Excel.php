<?php

namespace Eightbitsnl\NovaReports\Actions\Export;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Support\Facades\Storage; 

use Eightbitsnl\NovaReports\Exports\Excel as ExcelExport;

class Excel extends Action
{
	use InteractsWithQueue, Queueable;
	
    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach($models as $model)
        {
			
			(new ExcelExport)
				->forReport($model)
				->store( $model->export_path );
			
			return Action::download('/nova-vendor/eightbitsnl/nova-reports/download/'.$model->id, 'download.xlsx');
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields()
    {
        return [];
    }
}
