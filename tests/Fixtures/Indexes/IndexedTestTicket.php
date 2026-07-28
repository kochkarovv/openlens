<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Tests\Fixtures\Indexes;

use PDPhilip\ElasticLens\Builder\IndexBuilder;
use PDPhilip\ElasticLens\Builder\IndexField;
use PDPhilip\ElasticLens\IndexModel;
use PDPhilip\ElasticLens\Tests\Fixtures\Models\TestMessage;
use PDPhilip\ElasticLens\Tests\Fixtures\Models\TestTicket;

/**
 * Records how many times the index-existence check is performed, so tests can
 * assert that it never happens on the write path.
 */
class IndexedTestTicket extends IndexModel
{
    public static int $indexExistsCalls = 0;

    protected $baseModel = TestTicket::class;

    public static function indexExists(): bool
    {
        static::$indexExistsCalls++;

        return true;
    }

    public function fieldMap(): IndexBuilder
    {
        return IndexBuilder::map(TestTicket::class, function (IndexField $field) {
            $field->integer('id');
            $field->embedsMany('messages', TestMessage::class, 'test_ticket_id', 'id');
        });
    }
}
