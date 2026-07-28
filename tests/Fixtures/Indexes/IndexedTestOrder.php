<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Tests\Fixtures\Indexes;

use OpenSearch\Common\Exceptions\ServerErrorResponseException;
use PDPhilip\ElasticLens\Builder\IndexBuilder;
use PDPhilip\ElasticLens\IndexModel;
use PDPhilip\ElasticLens\Tests\Fixtures\Models\TestOrder;

/**
 * Stands in for an index whose cluster is unreachable: the existence check
 * fails the same way a 503 from OpenSearch does.
 */
class IndexedTestOrder extends IndexModel
{
    protected $baseModel = TestOrder::class;

    public static function indexExists(): bool
    {
        throw new ServerErrorResponseException('Unknown 503 error from OpenSearch null');
    }

    public function fieldMap(): IndexBuilder
    {
        return IndexBuilder::map(TestOrder::class);
    }
}
