<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use PDPhilip\ElasticLens\Observers\ObserverRegistry;
use PDPhilip\ElasticLens\Tests\Fixtures\Indexes\IndexedTestTicket;
use PDPhilip\ElasticLens\Tests\Fixtures\Models\TestMessage;

beforeEach(function () {
    config()->set('elasticlens.namespaces', [
        'PDPhilip\ElasticLens\Tests\Fixtures\Models' => 'PDPhilip\ElasticLens\Tests\Fixtures\Indexes',
    ]);

    // The watched model's table exists, but the base model's table deliberately
    // does not — so index maintenance fails while the write itself is fine.
    Schema::create('test_messages', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('test_ticket_id');
        $table->string('body')->nullable();
    });

    ObserverRegistry::registerWatcher(TestMessage::class, IndexedTestTicket::class);
});

it('still saves the watched model when index maintenance fails', function () {
    Exceptions::fake();

    $message = TestMessage::create(['test_ticket_id' => 1, 'body' => 'hello']);

    expect($message->exists)->toBeTrue();
    expect(TestMessage::count())->toBe(1);
});

it('reports index maintenance failures raised while saving', function () {
    Exceptions::fake();

    TestMessage::create(['test_ticket_id' => 1, 'body' => 'hello']);

    Exceptions::assertReported(QueryException::class);
});

it('still deletes the watched model when index maintenance fails', function () {
    Exceptions::fake();

    $message = TestMessage::withoutEvents(
        fn () => TestMessage::create(['test_ticket_id' => 1, 'body' => 'hello'])
    );

    $message->delete();

    expect(TestMessage::count())->toBe(0);
});
