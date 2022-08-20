<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTemplatefileFieldToReportsTable extends Migration
{
    public function up()
    {
        Schema::table("reports", function (Blueprint $table) {
            $table
                ->string("templatefile")
                ->nullable()
                ->after("title");
        });
    }

    public function down()
    {
        Schema::table("reports", function (Blueprint $table) {
            $table->dropColumn("templatefile");
        });
    }
}
