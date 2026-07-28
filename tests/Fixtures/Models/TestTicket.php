<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class TestTicket extends Model
{
    protected $table = 'test_tickets';

    protected $guarded = [];

    public $timestamps = false;
}
