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
     * @param  \Illuminate\Support\Collection  $reports
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $reports)
    {
        $action = config("nova-reports.exporter");

        /** @var \Eightbitsnl\NovaReports\Exports\Excel $reflect */
        $reflect = new ReflectionClass($action);

        throw_unless($reflect->isInstantiable(), new Exception("Nova Reports export class($action) is not instantiable!"));

        /** @var \Eightbitsnl\NovaReports\Models\Report $report */
        foreach ($reports as $report) {

            $exporter = $reflect->newInstance();

            $export_path = $report->export_path;

            $export_result = $exporter
                ->forReport($report)
                ->store($export_path, config('nova-reports.filesystem'));

            return $exporter->reportAvailableCallback($export_result, $export_path);
        }
    }
}
