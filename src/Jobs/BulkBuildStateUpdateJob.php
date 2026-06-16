<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PDPhilip\ElasticLens\Models\IndexableBuild;

class BulkBuildStateUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(private string $indexModel, private string $baseModel, private array $buildStates) {}

    public function handle(): void
    {
        if (! empty($this->buildStates)) {
            IndexableBuild::bulkWriteState(
                class_basename($this->baseModel),
                class_basename($this->indexModel),
                $this->buildStates,
                'Bulk Index'
            );
        }
    }
}
