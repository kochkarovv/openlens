<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class TestMessage extends Model
{
    protected $table = 'test_messages';

    protected $guarded = [];

    public $timestamps = false;
}
