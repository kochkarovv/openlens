<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PDPhilip\ElasticLens\Index\LensBuilder;
use PDPhilip\OpenSearch\Exceptions\QueryException;

class IndexDeletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private $indexModel, private $modelId)
    {
        if (config('elasticlens.queue')) {
            $this->onQueue(config('elasticlens.queue'));
        }
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            $builder = new LensBuilder($this->indexModel);
            $builder->processDelete($this->modelId);
        } catch (QueryException $e) {
            // 409 version_conflict with "no document was found" means the document
            // was already deleted (race condition between retries or concurrent jobs).
            // The desired end state is already achieved — treat as success.
            if (str_contains($e->getMessage(), 'version_conflict') &&
                str_contains($e->getMessage(), 'no document was found')) {
                return;
            }
            throw $e;
        }
    }
}
