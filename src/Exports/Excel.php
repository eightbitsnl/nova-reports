<?php

namespace Eightbitsnl\NovaReports\Exports;

use Eightbitsnl\NovaReports\Models\Report;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Events\BeforeWriting;
use Maatwebsite\Excel\Excel as MaatwebsiteExcel;
use Maatwebsite\Excel\Files\LocalTemporaryFile;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Style;
use Laravel\Nova\Actions\Action;

class Excel implements FromQuery, WithHeadings, WithMapping, WithProperties, ShouldAutoSize, WithEvents, WithDefaultStyles
{
    use Exportable;

    /**
     * The Callback that is fired when the Report is available for dwnload
     *
     * @param bool $store_result The result of the exporter store() method
     * @param string $export_path Where the file is or will be stored
     * @return array a Download Repsonse
     */
    public function reportAvailableCallback($store_result, $export_path)
    {
        return Action::download( route('nova-reports.download', ['report'=>$this->report->uuid]), "download.xlsx");
    }

    /** @var \Eightbitsnl\NovaReports\Models\Report */
    public Report $report;

    /**
     * Set $report property
     *
     * @param Report $report
     * @return static $this
     */
    public function forReport(Report $report)
    {
        $this->report = $report;
        return $this;
    }

    /**
     * Worksheet properties
     *
     * @return array array of worksheet properties
     */
    public function properties(): array
    {
        return [
            "creator" => config("app.name"),
            // 'lastModifiedBy' => '',
            "title" => $this->report->title,
            "description" => $this->report->note,
            // 'subject'        => '',
            // 'keywords'       => '',
            // 'category'       => '',
            // 'manager'        => '',
            // 'company'        => '',
        ];
    }

    /**
     * Map the data that needs to be added as row
     * @see https://docs.laravel-excel.com/3.1/exports/mapping.html
     *
     * @param mixed $model
     * @return array rows for the export
     */
    public function map($model): array
    {
        $rows = $this->report->getRowsForModel($model);

        return collect($rows)
            ->map(function ($r) {
                return collect($r)->values();
            })
            ->toArray();
    }

    /**
     * Add a heading row to the export
     * @see https://docs.laravel-excel.com/3.1/exports/mapping.html#adding-a-heading-row
     *
     * @return array heading row
     */
    public function headings(): array
    {
        return $this->report->getHeadings()->toArray();
    }

    /**
     * The query for the export. Behind the scenes this query is executed in chunks.
     * @see https://docs.laravel-excel.com/3.1/exports/from-query.html#from-query
     *
     * @return void
     */
    public function query()
    {
        return $this->report->getQuerybuilderInstance();
    }

    /**
     * Style styling columns, cells and rows.
     * @see https://docs.laravel-excel.com/3.1/exports/column-formatting.html#styling
     */
    // public function styles(Worksheet $sheet)
    // {
    //     $sheet
    //         ->getStyle("A:ZZ")
    //         ->getAlignment()
    //         ->setWrapText(true);
    // }

    public function defaultStyles(Style $defaultStyle)
    {
        // set alignment
        $defaultStyle
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // set wrap
        return $defaultStyle;
    }

    /**
     * Register events, to add custom behaviour to the export.
     * @see https://docs.laravel-excel.com/3.1/exports/extending.html#events
     *
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            BeforeWriting::class => function (BeforeWriting $event) {
                if (!empty($this->report->templatefile)) {
                    // load template
                    $templateFile = (new LocalTemporaryFile( tempnam(sys_get_temp_dir(), 'nova-reports-template') ))->copyFrom($this->report->templatefile, config('nova-reports.filesystem'));
                    
                    $event->writer->reopen($templateFile, MaatwebsiteExcel::XLSX);

                    // call the export on the first sheet
                    $event->writer->getSheetByIndex(0)->export($event->getConcernable());
                }
            },
        ];
    }
}
