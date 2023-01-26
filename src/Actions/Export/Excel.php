<?php

namespace Eightbitsnl\NovaReports\Actions\Export;

use Exception;
use ReflectionClass;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;

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
        $action = config("nova-reports.exporter");

        /** @var \Eightbitsnl\NovaReports\Exports\Excel $reflect */
        $reflect = new ReflectionClass($action);

        throw_unless($reflect->isInstantiable(), new Exception("Nova Reports export class($action) is not instantiable!"));

        /** @var \Eightbitsnl\NovaReports\Models\Report $model */
        foreach ($models as $model) {
            $reflect
                ->newInstance()
                ->forReport($model)
                ->store($model->export_path, config('nova-reports.filesystem'));

            return Action::download("/nova-vendor/eightbitsnl/nova-reports/download/" . $model->id, "download.xlsx");
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
