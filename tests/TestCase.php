<?php

namespace PDPhilip\ElasticLens\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use PDPhilip\ElasticLens\ElasticLensServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'PDPhilip\\Omnilens\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            ElasticLensServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // Index models resolve this connection on construction. Tests never reach
        // the wire, so it deliberately points at a host that isn't there.
        config()->set('database.connections.opensearch', [
            'driver' => 'opensearch',
            'hosts' => ['http://localhost:9200'],
        ]);

        /*
        $migration = include __DIR__.'/../database/migrations/create_omnilens_table.php.stub';
        $migration->up();
        */
    }
}
