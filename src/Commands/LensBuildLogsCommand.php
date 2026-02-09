<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Commands;

use Illuminate\Console\Command;
use OmniTerm\OmniTerm;
use PDPhilip\ElasticLens\Enums\IndexableBuildState;
use PDPhilip\ElasticLens\Models\IndexableBuild;

use function OmniTerm\render;

class LensBuildLogsCommand extends Command
{
    use OmniTerm;

    public $signature = 'lens:build-logs {indexModel? : The name of the index model} {--limit=50 : Limit the number of records} {--id= : View a specific build ID directly}';

    public $description = 'Browse and debug failed index builds';

    public function handle(): int
    {
        $this->initOmni();

        $this->newLine();
        render((string) view('elasticlens::cli.components.title', ['title' => 'Index Build Logs', 'color' => 'rose']));
        $this->newLine();

        $buildId = $this->option('id');
        $indexModel = $this->argument('indexModel');

        // Direct ID view
        if ($buildId) {
            return $this->showBuildDetail($buildId);
        }

        // Model-specific view
        if ($indexModel) {
            return $this->showModelBuilds($indexModel);
        }

        // Dashboard view
        return $this->showDashboard();
    }

    private function showDashboard(): int
    {
        $models = IndexableBuild::query()
            ->select('index_model')
            ->groupBy('index_model')
            ->get()
            ->pluck('index_model');

        if ($models->isEmpty()) {
            $this->omni->statusWarning('INFO', 'No build logs found');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->omni->header('Index Model', 'Failed', 'S/S/T');

        foreach ($models as $model) {
            $failed = IndexableBuild::countModelErrors($model);
            $skipped = IndexableBuild::countModelSkips($model);
            $total = IndexableBuild::countModelRecords($model);
            $success = $total - $failed - $skipped;

            $this->omni->row(
                $model,
                $failed,
                "{$skipped} / {$success} / {$total}",
                $failed > 0 ? 'text-rose-500' : 'text-emerald-500'
            );
        }

        $this->newLine();

        $options = $models->toArray();
        $options[] = 'Cancel';

        $selection = $this->omni->ask('Select an Index Model to view failed builds', $options);

        if ($selection !== 'Cancel') {
            $this->newLine();

            return $this->showModelBuilds($selection);
        }

        return self::SUCCESS;
    }

    private function showModelBuilds(string $indexModel): int
    {
        $indexModel = IndexableBuild::sanitizeIndexModelName($indexModel);
        $limit = (int) $this->option('limit');

        $builds = IndexableBuild::where('index_model', $indexModel)
            ->where('state', IndexableBuildState::FAILED)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        if ($builds->isEmpty()) {
            $this->omni->statusSuccess('INFO', 'No failed builds found for '.$indexModel);
            $this->newLine();

            return self::SUCCESS;
        }

        $this->omni->status('info', 'Failed Builds', 'Showing '.$builds->count().' most recent failures for '.$indexModel);
        $this->newLine();

        $this->omni->header('ID', 'Model ID', 'State / Snippet');

        foreach ($builds as $build) {
            $errorSnippet = $this->extractErrorSnippet($build);
            $this->omni->row(
                substr($build->id, 0, 8),
                $build->model_id,
                $build->state_name.' - '.$errorSnippet,
                'text-rose-500'
            );
        }

        $this->newLine();
        $this->omni->info('Run `lens:build-logs --id={id}` to view full details');
        $this->newLine();

        // Create options for the choice menu
        $options = $builds->mapWithKeys(function ($build) {
            $snippet = $this->extractErrorSnippet($build);
            $label = sprintf("[%s] ID: %s - %s", $build->model_id, substr($build->id, 0, 8), $snippet);
            return [$build->id => $label];
        })->toArray();

        $options['cancel'] = 'Cancel';

        $selection = $this->omni->ask('Select a build to view details', array_values($options));

        if ($selection !== 'Cancel') {
            // Find the ID by the label
            $buildId = array_search($selection, $options);
            if ($buildId) {
                $this->newLine();
                return $this->showBuildDetail($buildId);
            }
        }

        return self::SUCCESS;
    }

    private function showBuildDetail(string $buildId): int
    {
        // Try to find by full ID first, then by prefix
        $build = IndexableBuild::find($buildId);

        if (! $build && strlen($buildId) < 32) {
            // Since OpenSearch doesn't support wildcard/like on the internal _id field,
            // we can't do a partial match via query. 
            // We'll just return null and let the error handler catch it.
        }

        if (! $build) {
            $this->omni->statusError('ERROR', 'Build not found', ['ID: '.$buildId]);
            $this->newLine();

            return self::FAILURE;
        }

        $this->omni->status($build->state === IndexableBuildState::FAILED ? 'error' : 'warning', 'Build Details', $build->state_name);
        $this->newLine();

        $this->omni->header('Field', 'Value');
        $this->omni->row('Build ID', $build->id);
        $this->omni->row('Index Model', $build->index_model);
        $this->omni->row('Model', $build->model);
        $this->omni->row('Model ID', $build->model_id);
        $color = $build->state->color;
        $this->omni->row('State', $build->state_name, null, $color);
        $this->omni->row('Last Source', $build->last_source ?? 'N/A');
        $this->omni->row('Updated At', $build->updated_at?->format('Y-m-d H:i:s') ?? 'N/A');

        $this->newLine();

        // Show logs
        if (! empty($build->logs) && is_array($build->logs)) {
            $this->omni->info('Build Logs (Most Recent First)');
            $this->newLine();

            foreach ($build->logs as $index => $log) {
                $logNumber = $index + 1;
                $timestamp = isset($log['ts']) ? date('Y-m-d H:i:s', $log['ts']) : 'N/A';
                $success = $log['success'] ?? false;

                $this->omni->status(
                    $success ? 'success' : 'error',
                    "Log #{$logNumber}",
                    $timestamp
                );

                if (isset($log['data'])) {
                    $this->displayLogData($log['data']);
                }

                $this->newLine();
            }
        }

        return self::SUCCESS;
    }

    private function displayLogData(array $data): void
    {
        // Display error message
        if (isset($data['msg'])) {
            $this->omni->row('Message', $data['msg'], null, 'text-rose-500');
        }

        // Display details
        if (isset($data['details'])) {
            $this->omni->row('Details', '');
            $this->line('  '.str_replace("\n", "\n  ", trim($data['details'])));
        }

        // Display timing
        if (isset($data['took'])) {
            $took = $data['took'];
            $timing = sprintf('%.2fms', $took['ms'] ?? 0);
            $this->omni->row('Duration', $timing);
        }

        // Display the problematic data map
        if (isset($data['map']) && is_array($data['map'])) {
            $this->newLine();
            $this->omni->info('Problematic Record Data:');
            $this->newLine();

            foreach ($data['map'] as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : (string) $value;
                if (strlen($displayValue) > 100) {
                    $displayValue = substr($displayValue, 0, 97).'...';
                }
                $this->omni->row($key, $displayValue);
            }
        }
    }

    private function extractErrorSnippet(IndexableBuild $build): string
    {
        if (empty($build->logs) || ! is_array($build->logs)) {
            return 'No error details';
        }

        $latestLog = $build->logs[0] ?? null;
        if (! $latestLog || ! isset($latestLog['data'])) {
            return 'No error details';
        }

        $data = $latestLog['data'];

        // Try to get error message
        if (isset($data['msg'])) {
            return $this->truncate($data['msg'], 50);
        }

        // Try to get details
        if (isset($data['details'])) {
            $details = is_string($data['details']) ? $data['details'] : json_encode($data['details']);

            return $this->truncate($details, 50);
        }

        return 'See details';
    }

    private function truncate(string $text, int $length): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3).'...';
    }
}
