<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Commands;

use Exception;
use Illuminate\Console\Command;
use OmniTerm\OmniTerm;
use PDPhilip\ElasticLens\Commands\Scripts\HealthCheck;

use function OmniTerm\render;

class LensHealthCommand extends Command
{
    use OmniTerm;

    public $signature = 'lens:health {model : Base Model Name, example: User}';

    public $description = 'Full health check of the model & model index';

    /**
     * @throws Exception
     */
    public function handle(): int
    {
        $this->initOmni();
        $model = $this->argument('model');
        
        // Check for multiple matches first
        $modelCheck = \PDPhilip\ElasticLens\Commands\Scripts\QualifyModel::check($model);
        if (count($modelCheck['matches']) > 1) {
            $this->omni->statusWarning('MULTIPLE MODELS', 'Found multiple models with the same name', [
                'Please select which model you want to check:'
            ]);
            $this->newLine();
            
            $choices = [];
            foreach ($modelCheck['matches'] as $index => $matchedModel) {
                $choices[] = ($index + 1) . '. ' . $matchedModel;
            }
            
            $selection = $this->omni->ask('Select model (enter number)', $choices);
            
            // Extract the number from the selection
            if (preg_match('/^(\d+)\./', $selection, $matches)) {
                $selectedIndex = (int)$matches[1] - 1;
                if (isset($modelCheck['matches'][$selectedIndex])) {
                    $model = $modelCheck['matches'][$selectedIndex];
                } else {
                    $this->omni->statusError('ERROR', 'Invalid selection');
                    return self::FAILURE;
                }
            } else {
                $this->omni->statusError('ERROR', 'Invalid selection');
                return self::FAILURE;
            }
        }

        $loadError = HealthCheck::loadErrorCheck($model);
        $this->newLine();
        if ($loadError) {
            $this->omni->status($loadError['status'], $loadError['name'], $loadError['title'], $loadError['help']);
            $this->newLine();

            return self::FAILURE;
        }
        $health = HealthCheck::check($model);
        render((string) view('elasticlens::cli.components.title', ['title' => $health['title'], 'color' => 'emerald']));
        $this->omni->status($health['indexStatus']['status'], $health['indexStatus']['title'], $health['indexStatus']['name'], $health['indexStatus']['help'] ?? []);
        $this->newLine();
        $this->omni->header('Index Model', 'Value');
        foreach ($health['indexData'] as $detail => $value) {
            $this->omni->row($detail, $value);
        }
        $this->newLine();
        $this->omni->header('Base Model', 'Value');
        foreach ($health['modelData'] as $detail => $value) {
            $this->omni->row($detail, $value);
        }
        $this->newLine();
        $this->omni->header('Build Data', 'Value');
        foreach ($health['buildData'] as $detail => $value) {
            $this->omni->row($detail, $value);
        }
        $this->omni->status($health['configStatus']['status'], $health['configStatus']['name'], $health['configStatus']['title'], $health['configStatus']['help'] ?? []);
        $this->newLine();
        $this->omni->header('Config', 'Value');
        foreach ($health['configData'] as $detail => $value) {
            $this->omni->row($detail, $value);
        }
        $this->newLine();
        if (! $health['observers']) {
            $this->omni->warning('No observers found');
        } else {
            $this->omni->header('Observed Model', 'Type');
            foreach ($health['observers'] as $observer) {
                $this->omni->row($observer['key'], $observer['value']);
            }
        }
        if ($health['configStatusHelp']['critical'] || $health['configStatusHelp']['warning']) {
            $this->newLine();
            $this->omni->info('Config Help');
            if ($health['configStatusHelp']['critical']) {
                foreach ($health['configStatusHelp']['critical'] as $critical) {
                    $this->omni->statusError('Config Error', $critical['name'], $critical['help'] ?? []);
                    $this->newLine();
                }
            }
            if ($health['configStatusHelp']['warning']) {
                foreach ($health['configStatusHelp']['warning'] as $warning) {
                    $this->omni->statusWarning('Config Recommendation', $warning['name'], $warning['help'] ?? []);
                    $this->newLine();
                }
            }
        }

        return self::SUCCESS;
    }
}
