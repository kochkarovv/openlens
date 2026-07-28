<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use OpenSearch\Common\Exceptions\Missing404Exception;
use OpenSearch\Common\Exceptions\NoNodesAvailableException;
use OpenSearch\Common\Exceptions\ServerErrorResponseException;
use PDPhilip\ElasticLens\Observers\ObserverRegistry;
use PDPhilip\ElasticLens\Tests\Fixtures\Indexes\IndexedTestInvoice;
use PDPhilip\ElasticLens\Tests\Fixtures\Indexes\IndexedTestTicket;
use PDPhilip\ElasticLens\Tests\Fixtures\Models\TestInvoiceLine;
use PDPhilip\ElasticLens\Tests\Fixtures\Models\TestMessage;
use PDPhilip\OpenSearch\Exceptions\BuilderException;
use PDPhilip\OpenSearch\Exceptions\BulkInsertQueryException;
use PDPhilip\OpenSearch\Exceptions\QueryException as DriverQueryException;

beforeEach(function () {
    config()->set('elasticlens.namespaces', [
        'PDPhilip\ElasticLens\Tests\Fixtures\Models' => 'PDPhilip\ElasticLens\Tests\Fixtures\Indexes',
    ]);

    IndexedTestInvoice::$throwOnConstruct = null;

    // Watched models' tables exist. test_tickets deliberately does not, so the
    // ticket index's relation walk fails with a database error.
    Schema::create('test_messages', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('test_ticket_id');
        $table->string('body')->nullable();
    });
    Schema::create('test_invoice_lines', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('test_invoice_id');
        $table->string('body')->nullable();
    });
});

afterEach(function () {
    IndexedTestInvoice::$throwOnConstruct = null;
});

// --- Search-layer failures are absorbed -------------------------------------
//
// One case per exception class actually observed in Sentry for this codebase,
// so the catch list is pinned to real production failures rather than guesses.

$productionSearchFailures = [
    // SUPERVISOR-240 / 241 / 244 / 23Z - the outage that motivated this change
    'ServerErrorResponseException' => fn () => new ServerErrorResponseException('Unknown 503 error from OpenSearch null'),
    // SUPERVISOR-23X
    'NoNodesAvailableException' => fn () => new NoNodesAvailableException('No alive nodes found in your cluster'),
    // SUPERVISOR-242
    'Missing404Exception' => fn () => new Missing404Exception('{}'),
    // SUPERVISOR-1MG / 1MJ / 1MH - highest volume; extends Exception directly
    'driver QueryException' => fn () => new DriverQueryException(new Exception('mapper_parsing_exception')),
    // SUPERVISOR-23P / 22Y
    'BuilderException' => fn () => new BuilderException('date_from does not have a keyword field.'),
    // SUPERVISOR-239
    'BulkInsertQueryException' => fn () => new BulkInsertQueryException(['items' => []]),
];

foreach ($productionSearchFailures as $label => $makeException) {
    it("absorbs {$label} raised while saving a watched model", function () use ($makeException) {
        ObserverRegistry::registerWatcher(TestInvoiceLine::class, IndexedTestInvoice::class);
        Exceptions::fake();
        $exception = $makeException();
        IndexedTestInvoice::$throwOnConstruct = $exception;

        $line = TestInvoiceLine::create(['test_invoice_id' => 1, 'body' => 'hello']);

        expect($line->exists)->toBeTrue();
        expect(TestInvoiceLine::count())->toBe(1);
        Exceptions::assertReported($exception::class);
    });
}

it('still deletes the watched model when the search cluster is unavailable', function () {
    ObserverRegistry::registerWatcher(TestInvoiceLine::class, IndexedTestInvoice::class);
    Exceptions::fake();
    $line = TestInvoiceLine::withoutEvents(
        fn () => TestInvoiceLine::create(['test_invoice_id' => 1, 'body' => 'hello'])
    );
    IndexedTestInvoice::$throwOnConstruct = new ServerErrorResponseException('Unknown 503 error from OpenSearch null');

    $line->delete();

    expect(TestInvoiceLine::count())->toBe(0);
});

// --- Everything else stays loud ---------------------------------------------
//
// Nothing retries a failed relation walk or a failed queue dispatch, so
// swallowing one would commit the write and leave the index silently stale.

it('lets database failures propagate when saving', function () {
    ObserverRegistry::registerWatcher(TestMessage::class, IndexedTestTicket::class);

    expect(fn () => TestMessage::create(['test_ticket_id' => 1, 'body' => 'hello']))
        ->toThrow(QueryException::class);
});

it('lets database failures propagate when deleting', function () {
    ObserverRegistry::registerWatcher(TestMessage::class, IndexedTestTicket::class);
    $message = TestMessage::withoutEvents(
        fn () => TestMessage::create(['test_ticket_id' => 1, 'body' => 'hello'])
    );

    expect(fn () => $message->delete())->toThrow(QueryException::class);
});

it('lets unexpected runtime errors propagate when saving', function () {
    ObserverRegistry::registerWatcher(TestInvoiceLine::class, IndexedTestInvoice::class);
    IndexedTestInvoice::$throwOnConstruct = new RuntimeException('Connection refused [tcp://redis:6379]');

    expect(fn () => TestInvoiceLine::create(['test_invoice_id' => 1, 'body' => 'hello']))
        ->toThrow(RuntimeException::class);
});
