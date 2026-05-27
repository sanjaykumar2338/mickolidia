<?php

namespace App\Console\Commands;

use App\Services\MetaApi\MetaApiPoolAssignmentService;
use Illuminate\Console\Command;

class DiagnosePoolAssignment extends Command
{
    protected $signature = 'wolforix:diagnose-pool-assignment
        {login? : Optional MT5 login/account reference to inspect}
        {--server= : Restrict available-pool count to a server}
        {--source-pool= : Restrict available-pool count to a source pool}
        {--source-file= : Restrict available-pool count to a source file}
        {--json : Print JSON diagnostics}';

    protected $description = 'Diagnose MT5 pool availability and Phase 2 assignment readiness.';

    public function handle(MetaApiPoolAssignmentService $assignmentService): int
    {
        $diagnostic = $assignmentService->diagnose($this->argument('login') ? (string) $this->argument('login') : null, [
            'server' => $this->option('server'),
            'source_pool' => $this->option('source-pool'),
            'source_file' => $this->option('source-file'),
        ]);

        $this->info('Phase 2 pool assignment diagnostics');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Available pool rows', (string) data_get($diagnostic, 'pool.available', 0)],
            ['Allocated pool rows', (string) data_get($diagnostic, 'pool.allocated', 0)],
            ['Unassigned MetaApi rows', (string) data_get($diagnostic, 'pool.unassigned_metaapi_rows', 0)],
            ['Pool exhausted', data_get($diagnostic, 'pool.exhausted') ? 'yes' : 'no'],
            ['Assignment status', (string) data_get($diagnostic, 'assignment.status', '-')],
            ['Trading account', (string) data_get($diagnostic, 'assignment.trading_account.id', '-')],
            ['Pool entry', (string) data_get($diagnostic, 'assignment.pool_entry.id', '-')],
        ]);

        $this->printList('Warnings', (array) data_get($diagnostic, 'assignment.warnings', []));
        $this->printList('Errors', (array) data_get($diagnostic, 'assignment.errors', []));
        $this->printList('Recommendations', (array) ($diagnostic['recommendations'] ?? []));
        $this->printList('Assignment recommendations', (array) data_get($diagnostic, 'assignment.recommendations', []));

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[diagnostic unavailable]');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function printList(string $label, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->newLine();
        $this->info($label);

        foreach ($items as $item) {
            $this->line('- '.(string) $item);
        }
    }
}
