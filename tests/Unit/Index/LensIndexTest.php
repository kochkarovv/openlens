<?php

declare(strict_types=1);

use OpenSearch\Common\Exceptions\ServerErrorResponseException;
use PDPhilip\ElasticLens\Index\LensBuilder;
use PDPhilip\ElasticLens\Tests\Fixtures\Indexes\IndexedTestOrder;
use PDPhilip\ElasticLens\Tests\Fixtures\Indexes\IndexedTestTicket;

beforeEach(function () {
    IndexedTestTicket::$indexExistsCalls = 0;
});

it('does not touch the search cluster when constructing a builder', function () {
    new LensBuilder(IndexedTestTicket::class);

    expect(IndexedTestTicket::$indexExistsCalls)->toBe(0);
});

it('constructs a builder even when the search cluster is unavailable', function () {
    expect(fn () => new LensBuilder(IndexedTestOrder::class))
        ->not->toThrow(ServerErrorResponseException::class);
});

it('resolves index existence when explicitly asked', function () {
    $builder = new LensBuilder(IndexedTestTicket::class);

    expect($builder->checkIndexExists())->toBeTrue();
    expect(IndexedTestTicket::$indexExistsCalls)->toBe(1);
});

it('caches the index existence check', function () {
    $builder = new LensBuilder(IndexedTestTicket::class);

    $builder->checkIndexExists();
    $builder->checkIndexExists();

    expect(IndexedTestTicket::$indexExistsCalls)->toBe(1);
});
