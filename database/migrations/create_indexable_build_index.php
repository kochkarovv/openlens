<?php

use Illuminate\Database\Migrations\Migration;
use PDPhilip\OpenSearch\Schema\Blueprint;
use PDPhilip\OpenSearch\Schema\Schema;

return new class extends Migration
{
    public function up()
    {
        $connectionName = config('elasticlens.connection') ?? 'opensearch';

        Schema::on($connectionName)->deleteIfExists('indexable_builds');

        Schema::on($connectionName)->create('indexable_builds', function (Blueprint $index) {
            $index->keyword('model');
            $index->keyword('model_id');
            $index->keyword('index_model');
            $index->keyword('state');
            $index->keyword('last_source');
            $index->text('last_source');

            $index->property('object', 'state_data');
            $index->property('object', 'logs');
        });
    }

    public function down()
    {
        $connectionName = config('elasticlens.connection') ?? 'opensearch';

        Schema::on($connectionName)->deleteIfExists('indexable_builds');
    }
};
