<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Database;

use PDPhilip\OpenSearch\Connection as BaseConnection;

/**
 * Extends the pdphilip/opensearch Connection to avoid unnecessary scroll context
 * creation during model saves.
 *
 * The base driver's update() uses updateByQuery whenever body.query is present,
 * which is always the case with Eloquent's performUpdate() pattern:
 *   where($primaryKey, $id)->update($dirty)
 *
 * updateByQuery uses OpenSearch's scroll API internally. Under concurrent workers
 * each processing batches of IndexableBuild saves, scroll contexts quickly accumulate
 * and hit the 500-context limit.
 *
 * When the query targets a single document by _id, this override uses the direct
 * document update API instead (POST /{index}/_update/{id}), which opens no scroll
 * context at all.
 */
class Connection extends BaseConnection
{
    public function update($query, $bindings = []): mixed
    {
        if (isset($query['body']['query'])) {
            $directId = $this->extractSingleId($query['body']['query']);

            if ($directId !== null) {
                $directQuery = [
                    'index' => $query['index'],
                    'id'    => $directId,
                    'body'  => array_intersect_key($query['body'], array_flip(['script', 'doc'])),
                ];

                if (isset($query['refresh'])) {
                    $directQuery['refresh'] = $query['refresh'];
                }

                $response = $this->run($directQuery, $bindings, $this->connection->update(...));

                // processUpdate() expects ['updated' => int] (updateByQuery format).
                // Direct document update returns ['result' => 'updated'|'noop'|...].
                return ['updated' => in_array($response['result'] ?? '', ['updated', 'noop']) ? 1 : 0];
            }
        }

        $updateMethod = isset($query['body']['query']) ? 'updateByQuery' : 'update';

        return $this->run($query, $bindings, $this->connection->$updateMethod(...));
    }

    /**
     * Returns the document _id if the query targets exactly one document by _id,
     * or null if the query is broader (in which case updateByQuery is still needed).
     */
    private function extractSingleId(array $bodyQuery): ?string
    {
        // {"term": {"_id": "some-id"}}
        if (isset($bodyQuery['term']['_id'])) {
            $id = $bodyQuery['term']['_id'];

            return is_array($id) ? ($id['value'] ?? null) : (string) $id;
        }

        // {"bool": {"must": [{"term": {"_id": "some-id"}}]}}
        if (
            isset($bodyQuery['bool']['must'])
            && count($bodyQuery['bool']['must']) === 1
            && isset($bodyQuery['bool']['must'][0]['term']['_id'])
        ) {
            $id = $bodyQuery['bool']['must'][0]['term']['_id'];

            return is_array($id) ? ($id['value'] ?? null) : (string) $id;
        }

        return null;
    }
}
