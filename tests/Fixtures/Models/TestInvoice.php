<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class TestInvoice extends Model
{
    protected $table = 'test_invoices';

    protected $guarded = [];

    public $timestamps = false;
}
