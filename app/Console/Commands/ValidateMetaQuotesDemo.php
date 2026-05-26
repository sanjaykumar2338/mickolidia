<?php

namespace App\Console\Commands;

use App\Services\MetaApi\MetaQuotesDemoValidationService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ValidateMetaQuotesDemo extends Command
{
    protected $signature = 'metaquotes:validate-demo
        {--live : Call MetaApi. Without this flag the command only writes a dry-run architecture report}
        {--create-demo : Create MetaQuotes/MT5 demo accounts through MetaApi before registering them}
        {--count=1 : Number of demo accounts to create when --create-demo is used}
        {--login=* : Existing MT5 demo login to register in MetaApi}
        {--password=* : MT5 main password for --login; pass once for all logins or once per login}
        {--investor-password=* : Optional MT5 investor password for --login; pass once for all logins or once per login}
        {--server= : MT5 server name, default WOLFORIX_MT5_METAQUOTES_SERVER}
        {--email= : Demo holder email for --create-demo}
        {--name= : Demo holder name for --create-demo}
        {--phone= : Demo holder phone for --create-demo}
        {--account-type= : Broker account type for --create-demo}
        {--balance= : Demo starting balance}
        {--leverage= : Demo leverage}
        {--keyword=* : Broker search keyword; defaults to METAAPI_DEMO_KEYWORDS}
        {--store-pool : Store validated credentials in mt5_account_pool_entries}
        {--assign-account= : Assign one available MetaQuotes pool entry to this trading_accounts.id}
        {--metaapi-account-id= : Existing MetaApi account _id from dashboard; skips account create/import}
        {--debug-metaapi : Print sanitized MetaApi request/response bodies and include them in diagnostic JSON}
        {--pool=client_pool : Pool tag for stored entries}
        {--source-file= : Source file marker for stored entries}
        {--polls= : Terminal-state polls to run}
        {--poll-delay= : Seconds between terminal-state polls}
        {--history-days= : Days of order/deal history to validate}
        {--history-limit= : Max history orders/deals to request per MetaApi call}
        {--force-many : Allow more than the configured safe validation count}';

    protected $description = 'Validate Phase 1A MetaQuotes demo creation, MetaApi registration/sync, and MT5 pool assignment assumptions.';

    public function handle(MetaQuotesDemoValidationService $validationService): int
    {
        $this->info('Phase 1A MetaQuotes Demo Validation');

        try {
            $report = $validationService->run([
                'live' => (bool) $this->option('live'),
                'create_demo' => (bool) $this->option('create-demo'),
                'count' => (int) $this->option('count'),
                'login' => (array) $this->option('login'),
                'password' => (array) $this->option('password'),
                'investor_password' => (array) $this->option('investor-password'),
                'server' => $this->option('server'),
                'email' => $this->option('email'),
                'name' => $this->option('name'),
                'phone' => $this->option('phone'),
                'account_type' => $this->option('account-type'),
                'balance' => $this->option('balance'),
                'leverage' => $this->option('leverage'),
                'keyword' => (array) $this->option('keyword'),
                'store_pool' => (bool) $this->option('store-pool'),
                'assign_account' => $this->option('assign-account'),
                'metaapi_account_id' => $this->option('metaapi-account-id'),
                'debug_metaapi' => (bool) $this->option('debug-metaapi'),
                'pool' => $this->option('pool'),
                'source_file' => $this->option('source-file'),
                'polls' => $this->option('polls'),
                'poll_delay' => $this->option('poll-delay'),
                'history_days' => $this->option('history-days'),
                'history_limit' => $this->option('history-limit'),
                'force_many' => (bool) $this->option('force-many'),
            ]);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('MetaQuotes demo validation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Mode', (string) data_get($report, 'mode')],
            ['Token configured', data_get($report, 'config.metaapi_token_present') ? 'yes' : 'no'],
            ['Broker', (string) data_get($report, 'config.broker')],
            ['Server', (string) data_get($report, 'config.server')],
            ['Source file', (string) data_get($report, 'config.source_file')],
            ['Report', (string) data_get($report, 'report_path')],
            ['Stability', (string) data_get($report, 'stability.summary')],
        ]);

        $accounts = (array) data_get($report, 'accounts', []);

        if ($accounts !== []) {
            $this->newLine();
            $this->info('Account checks');
            $this->table(
                ['Login', 'MetaApi account', 'Create', 'Deploy', 'Polls', 'Pool'],
                array_map(static fn (array $account): array => [
                    (string) ($account['login'] ?? '-'),
                    (string) ($account['metaapi_account_id'] ?? '-'),
                    (string) data_get($account, 'create_account.status', '-'),
                    (string) data_get($account, 'deploy.status', '-'),
                    (string) count((array) ($account['polls'] ?? [])),
                    (string) data_get($account, 'pool_entry.status', '-'),
                ], $accounts),
            );
        }

        if (($assignment = data_get($report, 'pool.assignment')) !== null) {
            $this->newLine();
            $this->info('Pool assignment');
            $this->table(
                ['Field', 'Value'],
                collect((array) $assignment)
                    ->map(fn (mixed $value, string $key): array => [$key, is_scalar($value) || $value === null ? (string) ($value ?? '-') : json_encode($value)])
                    ->values()
                    ->all(),
            );
        }

        if ((bool) data_get($report, 'config.debug_metaapi', false)) {
            $this->newLine();
            $this->info('Sanitized MetaApi debug responses');

            foreach ((array) data_get($report, 'debug_metaapi.responses', []) as $response) {
                $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[debug response unavailable]');
            }
        }

        if ((string) data_get($report, 'mode') === 'dry-run') {
            $this->newLine();
            $this->warn('Dry-run complete. Add METAAPI_TOKEN and rerun with --live plus either --create-demo or --login/--password for end-to-end validation.');
        }

        return self::SUCCESS;
    }
}
