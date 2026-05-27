<?php

namespace App\Console\Commands;

use App\Services\MetaApi\MetaApiPoolAssignmentService;
use Illuminate\Console\Command;

class AssignPoolAccount extends Command
{
    protected $signature = 'wolforix:assign-pool-account
        {login : MT5 login/account reference to assign}
        {--account= : Explicit trading account login/reference/id-ish value}
        {--pool-login= : Specific unallocated MT5 pool login to assign}
        {--server= : Restrict assignment to a server}
        {--source-pool= : Restrict assignment to a source pool}
        {--source-file= : Restrict assignment to a source file}
        {--force : Allow account-size mismatch after admin review}
        {--dry-run : Show assignment plan without writing}
        {--json : Print JSON result}';

    protected $description = 'Safely assign an available MT5 pool account to a trading account for Phase 2 onboarding.';

    public function handle(MetaApiPoolAssignmentService $assignmentService): int
    {
        $result = $assignmentService->assign((string) $this->argument('login'), [
            'account' => $this->option('account'),
            'pool_login' => $this->option('pool-login'),
            'server' => $this->option('server'),
            'source_pool' => $this->option('source-pool'),
            'source_file' => $this->option('source-file'),
            'force' => (bool) $this->option('force'),
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        $this->info('Phase 2 pool assignment');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Identifier', (string) ($result['identifier'] ?? $this->argument('login'))],
            ['Status', (string) ($result['status'] ?? '-')],
            ['Trading account', (string) data_get($result, 'trading_account.id', '-')],
            ['Account reference', (string) data_get($result, 'trading_account.account_reference', '-')],
            ['Pool entry', (string) data_get($result, 'pool_entry.id', '-')],
            ['Pool login', (string) data_get($result, 'pool_entry.login', '-')],
            ['Server', (string) data_get($result, 'pool_entry.server', '-')],
            ['MetaApi account', (string) data_get($result, 'pool_entry.metaapi_account_id', '-')],
            ['Dry run', ! empty($result['dry_run']) ? 'yes' : 'no'],
        ]);

        $this->printList('Changes', (array) ($result['changes'] ?? []));
        $this->printList('Warnings', (array) ($result['warnings'] ?? []));
        $this->printList('Errors', (array) ($result['errors'] ?? []));
        $this->printList('Recommendations', (array) ($result['recommendations'] ?? []));

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[result unavailable]');
        }

        return in_array((string) ($result['status'] ?? ''), ['assigned', 'ready'], true)
            ? self::SUCCESS
            : self::FAILURE;
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
