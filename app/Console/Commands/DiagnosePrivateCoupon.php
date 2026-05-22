<?php

namespace App\Console\Commands;

use App\Models\ChallengePurchase;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\TradingAccount;
use App\Services\Pricing\ChallengePricingService;
use App\Services\Promotions\LaunchPromoRedemptionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DiagnosePrivateCoupon extends Command
{
    protected $signature = 'wolforix:diagnose-private-coupon
        {code : Private promo code to inspect}
        {--limit=25 : Maximum sample matches per searched column}';

    protected $description = 'Read-only diagnosis for hidden private coupon config, checkout schema, and redemption state.';

    /**
     * @var array<string, list<array{name: string, type: string, nullable: bool|null}>>
     */
    private array $columnCache = [];

    public function handle(
        ChallengePricingService $pricingService,
        LaunchPromoRedemptionService $redemptions,
    ): int {
        $code = trim((string) $this->argument('code'));
        $limit = max(1, min((int) $this->option('limit'), 100));

        $this->info('Read-only private coupon diagnosis');
        $this->warn('No writes are performed by this command.');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->line('Config cached: '.$this->yesNo(app()->configurationIsCached()));
        $this->newLine();

        $this->printConfigValues();

        try {
            DB::selectOne('select 1 as connected');
        } catch (Throwable $exception) {
            $this->newLine();
            $this->error('DB connection failed before schema diagnosis.');
            $this->line('DB error: '.$exception->getMessage());
            $this->line('No data was changed.');

            return self::FAILURE;
        }

        $tables = $this->tableNames();

        $this->printRelevantTables($tables);
        $this->printCheckoutModelTables();
        $this->printKeywordColumns($tables);
        $this->printCodeMatches($code, $tables, $limit);
        $this->printCheckoutDecision($code, $pricingService, $redemptions);

        return self::SUCCESS;
    }

    private function printConfigValues(): void
    {
        $this->info('Environment and config values');
        $this->table(['Key', '.env value', 'config value'], [
            [
                'PRIVATE_PROMO_ENABLED',
                $this->formatValue(env('PRIVATE_PROMO_ENABLED')),
                $this->yesNo((bool) config('wolforix.private_coupon.enabled', false)),
            ],
            [
                'PRIVATE_PROMO_CODE',
                $this->formatValue(env('PRIVATE_PROMO_CODE')),
                $this->formatValue(config('wolforix.private_coupon.code')),
            ],
            [
                'PRIVATE_PROMO_PERCENT',
                $this->formatValue(env('PRIVATE_PROMO_PERCENT')),
                $this->formatValue(config('wolforix.private_coupon.percent')),
            ],
            [
                'LAUNCH_PROMO_CODE',
                $this->formatValue(env('LAUNCH_PROMO_CODE')),
                $this->formatValue(config('wolforix.launch_discount.code')),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function tableNames(): array
    {
        try {
            return collect(Schema::getTables())
                ->map(fn (array $table): string => (string) ($table['name'] ?? $table['table_name'] ?? ''))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        } catch (Throwable) {
            return collect(Schema::getConnection()->getSchemaBuilder()->getTables())
                ->map(fn (array $table): string => (string) ($table['name'] ?? $table['table_name'] ?? ''))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }
    }

    /**
     * @param  list<string>  $tables
     */
    private function printRelevantTables(array $tables): void
    {
        $relevantTables = collect($tables)
            ->filter(static fn (string $table): bool => (bool) preg_match('/order|payment|purchase|challenge|promo|coupon|discount|checkout|invoice|transaction|account/i', $table))
            ->values();

        $this->newLine();
        $this->info('Relevant DB tables');

        if ($relevantTables->isEmpty()) {
            $this->warn('No relevant table names were found.');

            return;
        }

        $this->table(['table'], $relevantTables->map(fn (string $table): array => [$table])->all());
    }

    private function printCheckoutModelTables(): void
    {
        $this->newLine();
        $this->info('Checkout/payment/order completion model tables');
        $this->line('CheckoutController stores promo discounts on Order.metadata.launch_promo.');
        $this->line('OrderFulfillmentService marks Order.payment_status=paid and creates ChallengePurchase rows.');
        $this->line('Payment attempts use payment_attempts, not a payments table. No promo_redemptions table is used.');

        $rows = [];

        foreach ([Order::class, PaymentAttempt::class, ChallengePurchase::class, TradingAccount::class] as $modelClass) {
            /** @var Model $model */
            $model = new $modelClass;
            $table = $model->getTable();
            $columns = $this->columnsForTable($table);
            $columnNames = collect($columns)->pluck('name')->all();
            $interestingColumns = array_values(array_intersect($columnNames, [
                'id',
                'order_id',
                'order_number',
                'metadata',
                'meta',
                'payload',
                'discount_percent',
                'discount_amount',
                'final_price',
                'payment_status',
                'order_status',
                'status',
            ]));

            $rows[] = [
                class_basename($modelClass),
                $table,
                Schema::hasTable($table) ? 'yes' : 'no',
                $interestingColumns !== [] ? implode(', ', $interestingColumns) : '-',
            ];
        }

        $this->table(['model', 'table', 'exists', 'relevant columns'], $rows);
    }

    /**
     * @param  list<string>  $tables
     */
    private function printKeywordColumns(array $tables): void
    {
        $keywords = ['promo', 'coupon', 'discount', 'metadata', 'meta', 'final_price', 'payment_status', 'order_status'];
        $rows = [];

        foreach ($tables as $table) {
            foreach ($this->columnsForTable($table) as $column) {
                foreach ($keywords as $keyword) {
                    if (str_contains(strtolower($column['name']), $keyword)) {
                        $rows[] = [$table, $column['name'], $column['type']];
                        break;
                    }
                }
            }
        }

        $this->newLine();
        $this->info('Columns matching promo/coupon/discount/payment keywords');

        if ($rows === []) {
            $this->warn('No matching columns were found.');

            return;
        }

        $this->table(['table', 'column', 'type'], $rows);
    }

    /**
     * @param  list<string>  $tables
     */
    private function printCodeMatches(string $code, array $tables, int $limit): void
    {
        $rows = [];
        $skipped = [];

        if ($code === '') {
            $this->newLine();
            $this->warn('No code was provided for text/json column search.');

            return;
        }

        foreach ($tables as $table) {
            $columns = $this->columnsForTable($table);
            $sampleColumns = $this->sampleColumns($columns);

            foreach ($columns as $column) {
                if (! $this->isSafeSearchColumn($column)) {
                    continue;
                }

                try {
                    $matches = DB::table($table)
                        ->select($sampleColumns)
                        ->whereRaw($this->likeExpression($column['name']), ['%'.$code.'%'])
                        ->limit($limit)
                        ->get();
                } catch (QueryException $exception) {
                    $skipped[] = [$table, $column['name'], $this->shortError($exception)];

                    continue;
                }

                if ($matches->isEmpty()) {
                    continue;
                }

                $rows[] = [
                    $table,
                    $column['name'],
                    $column['type'],
                    (string) $matches->count(),
                    $matches->map(fn (object $row): string => $this->formatSampleRow($row))->implode(' | '),
                ];
            }
        }

        $this->newLine();
        $this->info('Search results for '.$code.' in safe text/json columns');

        if ($rows === []) {
            $this->line('No matches found.');
        } else {
            $this->table(['table', 'column', 'type', 'sample matches', 'sample rows'], $rows);
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('Some columns could not be searched safely.');
            $this->table(['table', 'column', 'reason'], array_slice($skipped, 0, 20));
        }
    }

    private function printCheckoutDecision(
        string $code,
        ChallengePricingService $pricingService,
        LaunchPromoRedemptionService $redemptions,
    ): void {
        $this->newLine();
        $this->info('Checkout decision for '.$this->formatValue($code));

        $promo = $pricingService->promoForCode($code);

        if ($promo === null) {
            $this->error('Checkout would return: Invalid or expired promo code.');
            $this->table(['Possible cause'], collect($this->invalidReasons($code, $pricingService))
                ->map(fn (string $reason): array => [$reason])
                ->all());

            return;
        }

        if (($promo['kind'] ?? null) === 'private_coupon') {
            $schemaProblems = $this->orderRedemptionSchemaProblems();

            if ($schemaProblems !== []) {
                $this->error('Checkout cannot safely enforce private coupon single-use with the current order schema.');
                $this->table(['Schema problem'], collect($schemaProblems)->map(fn (string $problem): array => [$problem])->all());

                return;
            }

            $used = $redemptions->promoCodeHasRedeemed((string) $promo['code']);

            if ($used) {
                $this->error('Checkout would return: This promo code has already been used.');
            } else {
                $this->info('Checkout should accept this as a private/manual-only coupon.');
                $this->line('Discount: '.number_format((float) ($promo['percent'] ?? 0), 2).'%');
                $this->line('If live checkout still says invalid, the running deployment is using stale config/cache or a different code/config version. Run config:clear and rebuild config cache after deploying.');
            }

            return;
        }

        $this->info('Checkout should accept this as the public launch promo.');
        $this->line('Discount: '.number_format((float) ($promo['percent'] ?? 0), 2).'%');
        $this->line('Public launch available: '.$this->yesNo($pricingService->publicLaunchDiscountAvailable()));
    }

    /**
     * @return list<string>
     */
    private function invalidReasons(string $code, ChallengePricingService $pricingService): array
    {
        $reasons = [];
        $privateEnabled = (bool) config('wolforix.private_coupon.enabled', false);
        $privateCode = trim((string) config('wolforix.private_coupon.code', ''));
        $launchCode = trim((string) config('wolforix.launch_discount.code', ''));

        if ($code === '') {
            $reasons[] = 'The submitted promo code is empty.';
        }

        if (! $privateEnabled) {
            $reasons[] = 'PRIVATE_PROMO_ENABLED resolves to false in config.';
        }

        if ($privateCode === '') {
            $reasons[] = 'PRIVATE_PROMO_CODE resolves to an empty value in config.';
        }

        if ($code !== '' && $privateCode !== '' && strcasecmp($code, $privateCode) !== 0) {
            $reasons[] = 'Submitted code does not match PRIVATE_PROMO_CODE case-insensitively.';
        }

        if ($code !== '' && $launchCode !== '' && strcasecmp($code, $launchCode) === 0 && ! $pricingService->publicLaunchDiscountAvailable()) {
            $reasons[] = 'Submitted code matches LAUNCH_PROMO_CODE, but the public launch discount is disabled or expired.';
        }

        if ($reasons === []) {
            $reasons[] = 'No local config reason found; check deployed code branch and run php artisan config:clear && php artisan config:cache.';
        }

        return $reasons;
    }

    /**
     * @return list<string>
     */
    private function orderRedemptionSchemaProblems(): array
    {
        $table = (new Order)->getTable();
        $problems = [];

        if (! Schema::hasTable($table)) {
            return ["Order model table [{$table}] is missing."];
        }

        foreach (['metadata', 'payment_status'] as $column) {
            if (! Schema::hasColumn($table, $column)) {
                $problems[] = "Order model table [{$table}] is missing required column [{$column}].";
            }
        }

        return $problems;
    }

    /**
     * @return list<array{name: string, type: string, nullable: bool|null}>
     */
    private function columnsForTable(string $table): array
    {
        if (array_key_exists($table, $this->columnCache)) {
            return $this->columnCache[$table];
        }

        if (! Schema::hasTable($table)) {
            return $this->columnCache[$table] = [];
        }

        try {
            $columns = collect(Schema::getColumns($table))
                ->map(function (array $column): array {
                    return [
                        'name' => (string) ($column['name'] ?? ''),
                        'type' => (string) ($column['type_name'] ?? $column['type'] ?? ''),
                        'nullable' => array_key_exists('nullable', $column) ? (bool) $column['nullable'] : null,
                    ];
                })
                ->filter(fn (array $column): bool => $column['name'] !== '')
                ->values()
                ->all();
        } catch (Throwable) {
            $columns = collect(Schema::getColumnListing($table))
                ->map(fn (string $column): array => [
                    'name' => $column,
                    'type' => 'unknown',
                    'nullable' => null,
                ])
                ->all();
        }

        return $this->columnCache[$table] = $columns;
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool|null}>  $columns
     * @return list<string>
     */
    private function sampleColumns(array $columns): array
    {
        $columnNames = collect($columns)->pluck('name')->all();
        $preferred = ['id', 'order_number', 'email', 'payment_status', 'order_status', 'status', 'created_at'];
        $sampleColumns = array_values(array_intersect($preferred, $columnNames));

        return $sampleColumns !== [] ? $sampleColumns : [reset($columnNames) ?: '*'];
    }

    /**
     * @param  array{name: string, type: string, nullable: bool|null}  $column
     */
    private function isSafeSearchColumn(array $column): bool
    {
        $name = strtolower($column['name']);
        $type = strtolower($column['type']);

        if ((bool) preg_match('/password|secret|token|private_key|api_key|credential|remember_token/i', $name)) {
            return false;
        }

        return (bool) preg_match('/char|text|json|enum|set|string/i', $type)
            || in_array($name, ['metadata', 'meta', 'payload'], true);
    }

    private function likeExpression(string $column): string
    {
        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);

        return match (DB::connection()->getDriverName()) {
            'pgsql' => "CAST({$wrapped} AS TEXT) ILIKE ?",
            'mysql', 'mariadb' => "CAST({$wrapped} AS CHAR) LIKE ?",
            'sqlsrv' => "CAST({$wrapped} AS NVARCHAR(MAX)) LIKE ?",
            default => "CAST({$wrapped} AS TEXT) LIKE ?",
        };
    }

    private function formatSampleRow(object $row): string
    {
        return collect((array) $row)
            ->map(fn (mixed $value, string $key): string => $key.'='.$this->formatValue($value))
            ->implode(', ');
    }

    private function shortError(Throwable $exception): string
    {
        $message = $exception->getPrevious()?->getMessage() ?: $exception->getMessage();

        return mb_strimwidth($message, 0, 120, '...');
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(not set)';
        }

        if (is_bool($value)) {
            return $this->yesNo($value);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[array]';
        }

        return (string) $value;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
