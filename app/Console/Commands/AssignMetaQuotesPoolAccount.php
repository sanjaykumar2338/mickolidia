<?php

namespace App\Console\Commands;

use App\Services\MetaQuotes\MetaQuotesPoolProvisioningService;
use Illuminate\Console\Command;

class AssignMetaQuotesPoolAccount extends Command
{
    protected $signature = 'wolforix:assign-metaquotes-pool-account
        {user : User id/email/name, trading account id/reference, or MT5 login}
        {--account= : Explicit trading account id/reference/login}
        {--pool-login= : Specific unallocated MT5 pool login to assign}
        {--server=FusionMarkets-Demo : Restrict assignment to a server}
        {--source-pool= : Restrict assignment to a source pool}
        {--source-file= : Restrict assignment to a source file}
        {--broker= : Restrict assignment to a broker}
        {--platform= : Restrict assignment to a platform}
        {--force : Allow account-size mismatch after admin review}
        {--dry-run : Show assignment plan without writing}
        {--json : Print JSON result}';

    protected $description = 'Assign a pre-created MetaQuotes/MT5 pool account into the onboarding flow.';

    public function handle(MetaQuotesPoolProvisioningService $provisioningService): int
    {
        $account = $provisioningService->resolveAccountForSubject(
            (string) $this->argument('user'),
            $this->option('account') ? (string) $this->option('account') : null,
        );

        if ($account === null) {
            $this->error('No trading account could be resolved for this user/account input.');

            return self::FAILURE;
        }

        $result = $provisioningService->provisionForAccount($account, [
            'pool_login' => $this->option('pool-login'),
            'server' => $this->option('server'),
            'source_pool' => $this->option('source-pool'),
            'source_file' => $this->option('source-file'),
            'broker' => $this->option('broker'),
            'platform' => $this->option('platform'),
            'force' => (bool) $this->option('force'),
            'dry_run' => (bool) $this->option('dry-run'),
            'source' => 'assign_metaquotes_pool_account_command',
        ]);

        $this->info('MetaQuotes pool assignment');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Status', (string) ($result['status'] ?? '-')],
            ['Dry run', ! empty($result['dry_run']) ? 'yes' : 'no'],
            ['Trading account', (string) data_get($result, 'trading_account.id', '-')],
            ['Account reference', (string) data_get($result, 'trading_account.account_reference', '-')],
            ['Assigned login', (string) data_get($result, 'pool_entry.login', data_get($result, 'trading_account.platform_login', '-'))],
            ['Server', (string) data_get($result, 'pool_entry.server', data_get($result, 'trading_account.platform_environment', '-'))],
            ['Pool source', trim((string) data_get($result, 'pool_entry.source_pool', '-').' / '.(string) data_get($result, 'pool_entry.source_file', '-'))],
            ['MetaApi UUID present', data_get($result, 'pool_entry.metaapi_account_id_present') ? 'yes' : 'no'],
            ['Onboarding state', (string) data_get($result, 'trading_account.onboarding_state', '-')],
        ]);

        $this->printList('Changes', (array) ($result['changes'] ?? []));
        $this->printList('Warnings', (array) ($result['warnings'] ?? []));
        $this->printList('Errors', (array) ($result['errors'] ?? []));
        $this->printList('Recommendations', (array) ($result['recommendations'] ?? []));

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[result unavailable]');
        }

        return in_array((string) ($result['status'] ?? ''), ['assigned', 'already_assigned', 'ready'], true)
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
