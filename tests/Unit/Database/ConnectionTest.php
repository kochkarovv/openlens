<?php

declare(strict_types=1);

use PDPhilip\ElasticLens\Database\Connection;

/**
 * Tests for Connection::extractSingleId() via reflection.
 *
 * extractSingleId() decides whether an OpenSearch update query targets a single
 * document by _id, allowing the driver to use the direct document update API
 * instead of updateByQuery (which opens a scroll context).
 *
 * If the driver ever changes its query shape and extractSingleId() silently
 * returns null, it will fall back to updateByQuery — reintroducing scroll
 * context exhaustion under concurrent workers.
 */

function callExtractSingleId(array $bodyQuery): ?string
{
    $connection = (new ReflectionClass(Connection::class))
        ->newInstanceWithoutConstructor();

    $method = new ReflectionMethod(Connection::class, 'extractSingleId');
    $method->setAccessible(true);

    return $method->invoke($connection, $bodyQuery);
}

it('extracts _id from a plain term query', function () {
    $query = ['term' => ['_id' => 'abc']];

    expect(callExtractSingleId($query))->toBe('abc');
});

it('extracts _id from a term query with array value form', function () {
    $query = ['term' => ['_id' => ['value' => 'abc']]];

    expect(callExtractSingleId($query))->toBe('abc');
});

it('extracts _id from a single-must bool query', function () {
    $query = [
        'bool' => [
            'must' => [
                ['term' => ['_id' => 'abc']],
            ],
        ],
    ];

    expect(callExtractSingleId($query))->toBe('abc');
});

it('returns null when bool must contains multiple clauses', function () {
    $query = [
        'bool' => [
            'must' => [
                ['term' => ['_id' => 'abc']],
                ['term' => ['status' => 'active']],
            ],
        ],
    ];

    expect(callExtractSingleId($query))->toBeNull();
});

it('returns null when _id is in bool filter instead of must', function () {
    $query = [
        'bool' => [
            'filter' => [
                ['term' => ['_id' => 'abc']],
            ],
        ],
    ];

    expect(callExtractSingleId($query))->toBeNull();
});

it('returns null when query has no _id at all', function () {
    expect(callExtractSingleId([]))->toBeNull();
});

it('casts integer _id to string in plain term query', function () {
    $query = ['term' => ['_id' => 1569]];

    expect(callExtractSingleId($query))->toBe('1569');
});

it('casts integer _id to string in array value form', function () {
    $query = ['term' => ['_id' => ['value' => 1569]]];

    expect(callExtractSingleId($query))->toBe('1569');
});

it('casts integer _id to string in single-must bool query', function () {
    $query = [
        'bool' => [
            'must' => [
                ['term' => ['_id' => 1569]],
            ],
        ],
    ];

    expect(callExtractSingleId($query))->toBe('1569');
});
