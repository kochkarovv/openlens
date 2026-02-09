<?php

declare(strict_types=1);

namespace PDPhilip\ElasticLens\Commands;

use Illuminate\Console\Command;
use OmniTerm\OmniTerm;
use PDPhilip\ElasticLens\Enums\IndexableMigrationLogState;
use PDPhilip\ElasticLens\Models\IndexableMigrationLog;

use function OmniTerm\render;

class LensMigrationLogsCommand extends Command
{
    use OmniTerm;

    public $signature = 'lens:migration-logs {indexModel? : The name of the index model} {--limit=50 : Limit the number of records} {--id= : View a specific migration log ID directly}';

    public $description = 'Browse migration history and debug failed migrations';

    public function handle(): int
    {
        $this->initOmni();

        $this->newLine();
        render((string) view('elasticlens::cli.components.title', ['title' => 'Migration Logs', 'color' => 'sky']));
        $this->newLine();

        $logId = $this->option('id');
        $indexModel = $this->argument('indexModel');

        // Direct ID view
        if ($logId) {
            return $this->showMigrationDetail($logId);
        }

        // Model-specific view
        if ($indexModel) {
            return $this->showModelMigrations($indexModel);
        }

        // Dashboard view
        return $this->showDashboard();
    }

    private function showDashboard(): int
    {
        $models = IndexableMigrationLog::query()
            ->select('index_model')
            ->groupBy('index_model')
            ->get()
            ->pluck('index_model');

        if ($models->isEmpty()) {
            $this->omni->statusWarning('INFO', 'No migration logs found');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->omni->header('Index Model', 'Latest Version', 'Total Migrations', 'Last Migration');

        foreach ($models as $model) {
            $latest = IndexableMigrationLog::getLatestMigration($model);
            $total = IndexableMigrationLog::where('index_model', $model)->count();

            $this->omni->row(
                $model,
                $latest?->version ?? 'N/A',
                $total,
                $latest?->created_at?->format('Y-m-d H:i:s') ?? 'N/A'
            );
        }

        $this->newLine();
        $this->omni->info('Run `lens:migration-logs {indexModel}` to view migration history for a specific model');
        $this->newLine();

        return self::SUCCESS;
    }

    private function showModelMigrations(string $indexModel): int
    {
        $indexModel = strtolower($indexModel);
        $limit = (int) $this->option('limit');

        $migrations = IndexableMigrationLog::where('index_model', $indexModel)
            ->orderBy('version_major', 'desc')
            ->orderBy('version_minor', 'desc')
            ->limit($limit)
            ->get();

        if ($migrations->isEmpty()) {
            $this->omni->statusWarning('INFO', 'No migration logs found for '.$indexModel);
            $this->newLine();

            return self::SUCCESS;
        }

        $this->omni->status('info', 'Migration History', 'Showing '.$migrations->count().' most recent migrations for '.$indexModel);
        $this->newLine();

        $this->omni->header('ID', 'Version', 'State', 'Created At');

        foreach ($migrations as $migration) {
            $color = match ($migration->state) {
                IndexableMigrationLogState::SUCCESS => 'text-emerald-500',
                IndexableMigrationLogState::FAILED => 'text-rose-500',
                IndexableMigrationLogState::UNDEFINED => 'text-amber-500',
                default => 'text-slate-500',
            };

            $this->omni->row(
                substr($migration->id, 0, 8),
                $migration->version,
                $migration->state->value,
                $migration->created_at?->format('Y-m-d H:i:s') ?? 'N/A',
                null,
                $color
            );
        }

        $this->newLine();
        $this->omni->info('Run `lens:migration-logs --id={id}` to view full details');
        $this->newLine();

        // Ask if user wants to view details
        $viewDetail = $this->omni->ask('View details for a specific migration?', ['yes', 'no']);

        if (in_array($viewDetail, ['yes', 'y'])) {
            $migrationId = $this->omni->ask('Enter migration ID (first 8 chars or full ID)');
            if ($migrationId) {
                $this->newLine();

                return $this->showMigrationDetail($migrationId);
            }
        }

        return self::SUCCESS;
    }

    private function showMigrationDetail(string $migrationId): int
    {
        // Try to find by full ID first, then by prefix
        $migration = IndexableMigrationLog::find($migrationId);

        if (! $migration && strlen($migrationId) < 32) {
            $migration = IndexableMigrationLog::query()
                ->where('id', 'like', $migrationId.'%')
                ->first();
        }

        if (! $migration) {
            $this->omni->statusError('ERROR', 'Migration log not found', ['ID: '.$migrationId]);
            $this->newLine();

            return self::FAILURE;
        }

        $statusType = match ($migration->state) {
            IndexableMigrationLogState::SUCCESS => 'success',
            IndexableMigrationLogState::FAILED => 'error',
            IndexableMigrationLogState::UNDEFINED => 'warning',
            default => 'info',
        };

        $this->omni->status($statusType, 'Migration Details', $migration->state->value);
        $this->newLine();

        $this->omni->header('Field', 'Value');
        $this->omni->row('Migration ID', $migration->id);
        $this->omni->row('Index Model', $migration->index_model);
        $this->omni->row('Version', $migration->version);
        $this->omni->row('State', $migration->state->value);
        $this->omni->row('Created At', $migration->created_at?->format('Y-m-d H:i:s') ?? 'N/A');

        $this->newLine();

        // Show map/schema or error
        if (! empty($migration->map) && is_array($migration->map)) {
            if (isset($migration->map['error'])) {
                $this->omni->statusError('ERROR', 'Migration Error', [$migration->map['error']]);
            } else {
                $this->omni->info('Migration Schema/Mappings:');
                $this->newLine();
                $this->displayMap($migration->map);
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function displayMap(array $map, int $indent = 0): void
    {
        $prefix = str_repeat('  ', $indent);

        foreach ($map as $key => $value) {
            if (is_array($value)) {
                $this->line($prefix.$key.':');
                $this->displayMap($value, $indent + 1);
            } else {
                $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                if (strlen($displayValue) > 100) {
                    $displayValue = substr($displayValue, 0, 97).'...';
                }
                $this->line($prefix.$key.': '.$displayValue);
            }
        }
    }
}
