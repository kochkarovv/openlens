<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class TestOrder extends Model
{
    protected $table = 'test_orders';

    protected $guarded = [];

    public $timestamps = false;
}
