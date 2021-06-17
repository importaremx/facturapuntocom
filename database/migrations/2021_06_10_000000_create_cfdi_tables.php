<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCfdiTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('importaremx_cfdi', function (Blueprint $table) {
            $table->increments('id');
            $table->string('xml',300)->nullable(true);
            $table->string('pdf',300)->nullable(true);
            $table->integer('status')->default(1);
            $table->text('json');
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->index(['owner_id', 'owner_type' ], 'importaremx_cfdi_owner_id_owner_type_index');
            $table->timestamps();
        });

        Schema::create('importaremx_taxpayer', function (Blueprint $table) {

            $table->increments('id');
            $table->string("uid");
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type' ], 'taxpayer_model_id_model_type_index');
            $table->timestamps();

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tableNames = config('permission.table_names');

        Schema::drop('importaremx_cfdi');
        Schema::drop('model_has_cfdi');

    }
}
