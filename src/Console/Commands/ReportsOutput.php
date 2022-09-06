<?php

namespace Eightbitsnl\NovaReports\Console\Commands;

use Eightbitsnl\NovaReports\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReportsOutput extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "nova-reports:output {id?}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Output the available reports";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if (empty($this->argument("id"))) {
            return $this->index();
        }

        return $this->show($this->argument("id"));
    }

    protected function index(): int
    {
        $rows = Report::all()->map(function ($report) {
            $report->row_count = $report->getCount();
            return $report->only("id", "title", "row_count");
        });

        $this->table(["id", "title", "count"], $rows->toArray());
        return static::SUCCESS;
    }

    protected function show(int $id): int
    {
        try {
            $report = Report::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            $this->error($e->getMessage());
            return static::FAILURE;
        }

        $headings = $report->getHeadings()->toArray();
        $rows = $report->getRows()->toArray();

        $this->alert($report->title);

        $this->table($headings, $rows);
        return static::SUCCESS;
    }
}
