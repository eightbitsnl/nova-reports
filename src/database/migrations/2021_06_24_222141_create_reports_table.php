<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReportsTable extends Migration
{
    public function up()
    {
        Schema::create("reports", function (Blueprint $table) {
            $table->id();

            $table->uuid("uuid")->unique();

            $table->string("title");
            $table->text("note")->nullable();

            $table->string("entrypoint")->nullable();
            $table->text("loadrelation")->nullable();

            $table->text("query")->nullable();
            $table->text("export_fields")->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists("reports");
    }
}
