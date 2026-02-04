<?php

use Illuminate\Database\Migrations\Migration;
use PDPhilip\OpenSearch\Schema\Blueprint;
use PDPhilip\OpenSearch\Schema\Schema;

return new class extends Migration
{
    public function up()
    {
        $connectionName = config('elasticlens.connection') ?? 'opensearch';

        Schema::on($connectionName)->deleteIfExists('indexable_migration_logs');

        Schema::on($connectionName)->create('indexable_migration_logs', function (Blueprint $index) {
            $index->keyword('index_model');
            $index->keyword('state');
            $index->integer('version_major');
            $index->integer('version_minor');
            $index->property('object', 'map');
        });
    }

    public function down()
    {
        $connectionName = config('elasticlens.connection') ?? 'opensearch';

        Schema::on($connectionName)->deleteIfExists('indexable_migration_logs');
    }
};
