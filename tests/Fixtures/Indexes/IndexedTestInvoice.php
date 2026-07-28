<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Tests\Fixtures\Indexes;

use PDPhilip\ElasticLens\Builder\IndexBuilder;
use PDPhilip\ElasticLens\Builder\IndexField;
use PDPhilip\ElasticLens\IndexModel;
use PDPhilip\ElasticLens\Tests\Fixtures\Models\TestInvoice;
use PDPhilip\ElasticLens\Tests\Fixtures\Models\TestInvoiceLine;
use Throwable;

/**
 * Simulates a failing cluster on demand: constructing the index model is what
 * resolves the OpenSearch connection, so a test can fail that step with a
 * chosen exception once the observer has already been registered.
 */
class IndexedTestInvoice extends IndexModel
{
    public static ?Throwable $throwOnConstruct = null;

    protected $baseModel = TestInvoice::class;

    public function __construct()
    {
        parent::__construct();

        if (static::$throwOnConstruct !== null) {
            throw static::$throwOnConstruct;
        }
    }

    public function fieldMap(): IndexBuilder
    {
        return IndexBuilder::map(TestInvoice::class, function (IndexField $field) {
            $field->integer('id');
            $field->embedsMany('lines', TestInvoiceLine::class, 'test_invoice_id', 'id');
        });
    }
}
