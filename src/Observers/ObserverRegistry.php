<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Observers;

use Exception;
use OpenSearch\Common\Exceptions\OpenSearchException;
use PDPhilip\ElasticLens\Lens;
use PDPhilip\ElasticLens\Watchers\EmbeddedModelTrigger;
use PDPhilip\OpenSearch\Exceptions\LaravelOpenSearchException;
use PDPhilip\OpenSearch\Exceptions\QueryException;

class ObserverRegistry
{
    /**
     * @throws Exception
     */
    public static function register($baseModel): void
    {

        $indexModel = Lens::fetchIndexModelClass($baseModel);

        if (! class_exists($indexModel)) {
            return;
        }
        $observers = (new $indexModel)->getObserverSet();

        if (! empty($observers['base'])) {
            $baseModel::observe(new BaseModelObserver);
        }
        if (! empty($observers['embedded'])) {
            foreach ($observers['embedded'] as $settings) {
                if ($settings['observe']) {
                    $embeddedModel = $settings['relation'];
                    if (! Lens::checkIfWatched($embeddedModel, $indexModel)) {
                        self::watchEmbedded($embeddedModel, $settings, $baseModel);
                    }
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    public static function registerWatcher($watchedModel, $indexModel): void
    {
        $indexModelInstance = new $indexModel;
        $observers = $indexModelInstance->getObserverSet();
        $baseModel = $indexModelInstance->getBaseModel();
        if (! empty($observers['embedded'])) {
            foreach ($observers['embedded'] as $settings) {
                if ($watchedModel == $settings['relation'] && $settings['observe']) {
                    self::watchEmbedded($watchedModel, $settings, $baseModel);
                }

            }
        }
    }

    /**
     * @throws Exception
     */
    private static function watchEmbedded($watchedModel, $settings, $baseModel): void
    {
        $watchedModel::saved(function ($model) use ($settings, $baseModel) {
            self::trigger($model, $baseModel, $settings, 'saved');
        });
        $watchedModel::deleted(function ($model) use ($settings, $baseModel) {
            self::trigger($model, $baseModel, $settings, 'deleted');
        });
    }

    /**
     * Index maintenance runs inside the write that triggered it, so an
     * unreachable search cluster must not fail every save and delete of a
     * watched model. Those failures are reported and swallowed: the queued
     * build is the retry, and a rebuild or sync reconciles the index.
     *
     * Only search-layer failures are absorbed. Database and queue-dispatch
     * errors still propagate, because nothing would retry them - swallowing
     * one would commit the write, drop the reindex on the floor and leave the
     * index silently stale with no record of what was lost.
     *
     * QueryException is listed separately: unlike the driver's other
     * exceptions it extends Exception directly rather than
     * LaravelOpenSearchException.
     */
    private static function trigger($model, $baseModel, $settings, string $event): void
    {
        try {
            $watcher = new EmbeddedModelTrigger($model, $baseModel, $settings);
            $watcher->handle($event);
        } catch (OpenSearchException|LaravelOpenSearchException|QueryException $e) {
            report($e);
        }
    }
}
