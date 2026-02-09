<?php

namespace PDPhilip\ElasticLens\Commands\Scripts;

class QualifyModel
{
    public static function check($model)
    {
        // First, check if the model is already a full class name
        if (str_contains($model, '\\') && class_exists($model)) {
            return [
                'qualified' => $model,
                'matches' => [$model],
                'notFound' => [],
            ];
        }
        
        // Otherwise, try to resolve it using configured namespaces
        $modelNameSpaces = config('elasticlens.namespaces');
        $found = [];
        $notFound = [];
        
        foreach ($modelNameSpaces as $modelNameSpace => $indexNameSpace) {
            $modelPath = $modelNameSpace.'\\'.$model;
            if (class_exists($modelPath)) {
                $found[] = $modelPath;
            } else {
                $notFound[] = $modelPath;
            }
        }

        return [
            'qualified' => count($found) === 1 ? $found[0] : null,
            'matches' => $found,
            'notFound' => $notFound,
        ];
    }
}
