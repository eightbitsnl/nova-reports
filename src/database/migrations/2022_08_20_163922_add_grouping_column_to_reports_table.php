<?php

use Eightbitsnl\NovaReports\Models\Report;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGroupingColumnToReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table("reports", function (Blueprint $table) {
            $table
                ->enum("grouping_option", [Report::OUTPUT_TYPE_CROSSJOIN, Report::OUTPUT_TYPE_FLAT])
                ->default(Report::OUTPUT_TYPE_CROSSJOIN)
                ->after("export_fields");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table("reports", function (Blueprint $table) {
            $table->dropColumn("grouping_option");
        });
    }
}
