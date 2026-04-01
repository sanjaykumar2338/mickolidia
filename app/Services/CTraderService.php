<?php

namespace App\Services;

use App\Models\CTraderConnection;
use App\Models\TradingAccount;
use App\Models\User;
use App\Support\CTrader\CTraderAuthorizationRequiredException;
use App\Support\CTrader\CTraderTokenExpiredException;
use App\Support\CTrader\OpenApiClient;
use App\Support\CTrader\PayloadType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CTraderService
{
    public function isConfigured(): bool
    {
        return filled(config('services.ctrader.client_id'))
            && filled(config('services.ctrader.client_secret'))
            && filled(config('services.ctrader.auth_url'))
            && filled(config('services.ctrader.token_url'));
    }

    public function buildAuthorizationUrl(): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('cTrader client credentials are missing.');
        }

        return (string) config('services.ctrader.auth_url').'?'.http_build_query([
            'client_id' => config('services.ctrader.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => config('services.ctrader.scope', 'accounts'),
            'product' => 'web',
        ]);
    }

    public function authorizeUser(User $user, string $code): CTraderConnection
    {
        $tokenPayload = $this->exchangeAuthorizationCode($code);

        $connection = CTraderConnection::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'token_type' => (string) Arr::get($tokenPayload, 'tokenType', 'bearer'),
                'scope' => (string) config('services.ctrader.scope', 'accounts'),
                'access_token' => (string) Arr::get($tokenPayload, 'accessToken'),
                'refresh_token' => (string) Arr::get($tokenPayload, 'refreshToken'),
                'expires_at' => now()->addSeconds((int) Arr::get($tokenPayload, 'expiresIn', 0)),
                'last_refreshed_at' => now(),
                'last_authorized_at' => now(),
                'last_error' => null,
                'last_error_at' => null,
            ],
        );

        try {
            $authorizedAccounts = $this->getAuthorizedAccounts($connection, forceRefresh: true);
            $linkedCount = $this->autoLinkTradingAccounts($user, $connection, $authorizedAccounts);

            $connection->forceFill([
                'meta' => array_merge($connection->meta ?? [], [
                    'linked_accounts' => $linkedCount,
                ]),
                'last_error' => null,
                'last_error_at' => null,
            ])->save();
        } catch (Throwable $exception) {
            $this->logFailure('OAuth callback authorization failed.', $exception, [
                'user_id' => $user->id,
            ]);

            $connection->forceFill([
                'last_error' => $exception->getMessage(),
                'last_error_at' => now(),
            ])->save();

            throw $exception;
        }

        return $connection->fresh();
    }

    public function linkAuthorizedAccountToTradingAccount(TradingAccount $account, string $platformAccountId): TradingAccount
    {
        $account->loadMissing(['user.ctraderConnection', 'ctraderConnection']);

        $connection = $account->ctraderConnection ?? $account->user?->ctraderConnection;

        if (! $connection instanceof CTraderConnection) {
            throw new CTraderAuthorizationRequiredException('Authorize Wolforix with cTrader before linking an account.');
        }

        $authorizedAccount = collect($this->getAuthorizedAccounts($connection))
            ->first(fn (array $row): bool => (string) Arr::get($row, 'ctid_trader_account_id') === trim($platformAccountId));

        if (! is_array($authorizedAccount)) {
            throw new RuntimeException('The selected cTrader account is not authorized for this user.');
        }

        $duplicate = TradingAccount::query()
            ->where('id', '!=', $account->id)
            ->where('platform_slug', 'ctrader')
            ->where('platform_account_id', (string) Arr::get($authorizedAccount, 'ctid_trader_account_id'))
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('This cTrader account is already linked to another Wolforix trading account.');
        }

        $this->linkTradingAccount($account, $connection, $authorizedAccount);

        return $account->fresh(['ctraderConnection']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAuthorizedAccounts(CTraderConnection|User $source, bool $forceRefresh = false): array
    {
        $connection = $source instanceof CTraderConnection
            ? $source
            : $source->ctraderConnection;

        if (! $connection instanceof CTraderConnection) {
            return [];
        }

        if (! $forceRefresh && is_array($connection->authorized_accounts) && $connection->authorized_accounts !== []) {
            return $connection->authorized_accounts;
        }

        $connection = $this->ensureFreshAccessToken($connection);
        $accessToken = $this->tokenForConnection($connection);

        $accounts = $this->withPlatformClient('demo', function (OpenApiClient $client) use ($accessToken): array {
            $this->applicationAuth($client);

            return $client->request(
                PayloadType::GET_ACCOUNTS_BY_ACCESS_TOKEN_REQ,
                ['accessToken' => $accessToken],
                [PayloadType::GET_ACCOUNTS_BY_ACCESS_TOKEN_RES],
            );
        }, retryLive: true);

        $normalized = collect(Arr::get($accounts, 'ctidTraderAccount', []))
            ->filter(fn ($account): bool => is_array($account))
            ->map(fn (array $account): array => [
                'ctid_trader_account_id' => (string) Arr::get($account, 'ctidTraderAccountId'),
                'is_live' => (bool) Arr::get($account, 'isLive', false),
                'environment' => (bool) Arr::get($account, 'isLive', false) ? 'live' : 'demo',
                'trader_login' => Arr::has($account, 'traderLogin') ? (string) Arr::get($account, 'traderLogin') : null,
                'broker_title_short' => Arr::get($account, 'brokerTitleShort'),
                'last_closing_deal_timestamp' => Arr::get($account, 'lastClosingDealTimestamp'),
                'last_balance_update_timestamp' => Arr::get($account, 'lastBalanceUpdateTimestamp'),
            ])
            ->values()
            ->all();

        $connection->forceFill([
            'authorized_accounts' => $normalized,
            'last_synced_accounts_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
        ])->save();

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccountInfo(TradingAccount $account): array
    {
        return Arr::get($this->fetchAccountDataset($account), 'account_info', []);
    }

    public function getAccountBalance(TradingAccount $account): float
    {
        return (float) Arr::get($this->syncAccountData($account), 'balance', 0);
    }

    public function getAccountEquity(TradingAccount $account): float
    {
        return (float) Arr::get($this->syncAccountData($account), 'equity', 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getOpenPositions(TradingAccount $account): array
    {
        return Arr::get($this->fetchAccountDataset($account), 'open_positions', []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTradeHistory(TradingAccount $account): array
    {
        return Arr::get($this->fetchAccountDataset($account), 'trade_history', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncAccountData(TradingAccount $account): array
    {
        $dataset = $this->fetchAccountDataset($account);
        $authorizedAccount = Arr::get($dataset, 'authorized_account', []);
        $accountInfo = Arr::get($dataset, 'account_info', []);
        $openPositions = collect(Arr::get($dataset, 'open_positions', []));
        $tradeHistory = collect(Arr::get($dataset, 'trade_history', []));

        $startingBalance = (float) ($account->starting_balance ?: $account->account_size ?: 0);
        $balance = (float) Arr::get($accountInfo, 'balance', $account->balance ?? $startingBalance);
        $equity = (float) Arr::get($accountInfo, 'equity', $account->equity ?? $balance);
        $todayProfit = round($this->sumClosedProfitForDay($tradeHistory), 2);
        $dailyDrawdown = $this->calculateDailyDrawdown($account, $equity);
        $maxDrawdown = $this->calculateMaxDrawdown($account, $balance, $equity);
        $drawdownPercent = $startingBalance > 0 ? round(($maxDrawdown / $startingBalance) * 100, 2) : 0.0;
        $tradingDays = $this->countTradingDays($tradeHistory, $openPositions, $account);
        $totalProfit = round($balance - $startingBalance, 2);
        $profitLoss = round($equity - $startingBalance, 2);

        return [
            'platform_account_id' => Arr::get($authorizedAccount, 'ctid_trader_account_id', $account->platform_account_id),
            'platform_login' => Arr::get($authorizedAccount, 'trader_login', $account->platform_login),
            'platform_environment' => Arr::get($authorizedAccount, 'environment', $account->platform_environment ?: config('services.ctrader.environment', 'demo')),
            'platform_status' => 'live_connected',
            'balance' => $balance,
            'equity' => $equity,
            'profit_loss' => $profitLoss,
            'total_profit' => $totalProfit,
            'today_profit' => $todayProfit,
            'daily_drawdown' => $dailyDrawdown,
            'max_drawdown' => $maxDrawdown,
            'drawdown_percent' => $drawdownPercent,
            'trading_days_completed' => $tradingDays,
            'account_phase' => $this->phaseKey($account),
            'phase_index' => max((int) $account->phase_index, 1),
            'account_status' => $account->account_status ?: 'active',
            'is_funded' => (bool) $account->is_funded,
            'stage' => $account->stage ?: $this->stageLabel($account),
            'activated_at' => optional($account->activated_at ?? $account->created_at)->toIso8601String(),
            'synced_at' => now()->toIso8601String(),
            'raw' => [
                'authorized_account' => $authorizedAccount,
                'account_info' => $accountInfo,
                'open_positions' => $openPositions->all(),
                'trade_history' => $tradeHistory->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAccountDataset(TradingAccount $account, bool $retried = false): array
    {
        $account->loadMissing(['user.ctraderConnection', 'ctraderConnection']);

        $connection = $account->ctraderConnection ?? $account->user?->ctraderConnection;

        if (! $connection instanceof CTraderConnection && blank(config('services.ctrader.access_token'))) {
            throw new CTraderAuthorizationRequiredException('This trading account is not authorized with cTrader yet.');
        }

        try {
            $resolved = $this->resolveAuthorizedAccount($account, $connection);
            $accessToken = $connection instanceof CTraderConnection
                ? $this->tokenForConnection($this->ensureFreshAccessToken($connection))
                : (string) config('services.ctrader.access_token');
            $environment = (string) Arr::get($resolved, 'environment', $account->platform_environment ?: config('services.ctrader.environment', 'demo'));
            $accountId = (int) Arr::get($resolved, 'ctid_trader_account_id');
            $accountLogin = Arr::get($resolved, 'trader_login');
            $from = $this->historyFrom($account);
            $to = now();

            $payload = $this->withPlatformClient($environment, function (OpenApiClient $client) use ($accessToken, $accountId, $from, $to): array {
                $this->applicationAuth($client);
                $client->request(
                    PayloadType::ACCOUNT_AUTH_REQ,
                    [
                        'ctidTraderAccountId' => $accountId,
                        'accessToken' => $accessToken,
                    ],
                    [PayloadType::ACCOUNT_AUTH_RES],
                );

                $trader = $client->request(
                    PayloadType::TRADER_REQ,
                    ['ctidTraderAccountId' => $accountId],
                    [PayloadType::TRADER_RES],
                );
                $reconcile = $client->request(
                    PayloadType::RECONCILE_REQ,
                    [
                        'ctidTraderAccountId' => $accountId,
                        'returnProtectionOrders' => true,
                    ],
                    [PayloadType::RECONCILE_RES],
                );
                $pnl = $client->request(
                    PayloadType::GET_POSITION_UNREALIZED_PNL_REQ,
                    ['ctidTraderAccountId' => $accountId],
                    [PayloadType::GET_POSITION_UNREALIZED_PNL_RES],
                );
                $deals = $client->request(
                    PayloadType::DEAL_LIST_REQ,
                    [
                        'ctidTraderAccountId' => $accountId,
                        'fromTimestamp' => $from->getTimestampMs(),
                        'toTimestamp' => $to->getTimestampMs(),
                        'maxRows' => (int) config('trading.platforms.ctrader.history_max_rows', 500),
                    ],
                    [PayloadType::DEAL_LIST_RES],
                );

                return compact('trader', 'reconcile', 'pnl', 'deals');
            });

            $accountInfo = $this->normalizeAccountInfo($payload, $accountId, $accountLogin);

            if ($connection instanceof CTraderConnection) {
                $account->forceFill([
                    'ctrader_connection_id' => $connection->id,
                    'platform_account_id' => (string) $accountId,
                    'platform_login' => $accountLogin ? (string) $accountLogin : $account->platform_login,
                    'platform_environment' => $environment,
                ])->saveQuietly();
            }

            return [
                'authorized_account' => [
                    'ctid_trader_account_id' => (string) $accountId,
                    'environment' => $environment,
                    'trader_login' => $accountLogin ? (string) $accountLogin : $account->platform_login,
                ],
                'account_info' => $accountInfo,
                'open_positions' => $this->normalizeOpenPositions($payload),
                'trade_history' => $this->normalizeDeals($payload),
            ];
        } catch (CTraderTokenExpiredException $exception) {
            if ($retried || ! $connection instanceof CTraderConnection) {
                $this->logFailure('cTrader token expired during sync.', $exception, [
                    'trading_account_id' => $account->id,
                ]);

                throw $exception;
            }

            $this->ensureFreshAccessToken($connection, force: true);

            return $this->fetchAccountDataset($account->fresh(['user.ctraderConnection', 'ctraderConnection']), true);
        } catch (Throwable $exception) {
            $this->logFailure('cTrader account dataset fetch failed.', $exception, [
                'trading_account_id' => $account->id,
            ]);

            if ($connection instanceof CTraderConnection) {
                $connection->forceFill([
                    'last_error' => $exception->getMessage(),
                    'last_error_at' => now(),
                ])->save();
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeAuthorizationCode(string $code): array
    {
        return $this->exchangeToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'client_id' => config('services.ctrader.client_id'),
            'client_secret' => config('services.ctrader.client_secret'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshAccessToken(CTraderConnection $connection): array
    {
        if (blank($connection->refresh_token)) {
            throw new RuntimeException('The cTrader connection is missing a refresh token.');
        }

        return $this->exchangeToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'client_id' => config('services.ctrader.client_id'),
            'client_secret' => config('services.ctrader.client_secret'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function exchangeToken(array $query): array
    {
        try {
            $response = Http::timeout((int) config('services.ctrader.timeout', 15))
                ->acceptJson()
                ->get((string) config('services.ctrader.token_url'), $query)
                ->throw();
        } catch (RequestException $exception) {
            $this->logFailure('cTrader token exchange failed.', $exception, [
                'query_keys' => array_keys($query),
            ]);

            throw $exception;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('cTrader token exchange returned an invalid response.');
        }

        if (filled(Arr::get($payload, 'errorCode'))) {
            throw new RuntimeException(trim((string) Arr::get($payload, 'errorCode').' '.Arr::get($payload, 'description')));
        }

        return $payload;
    }

    private function ensureFreshAccessToken(CTraderConnection $connection, bool $force = false): CTraderConnection
    {
        if (! $force && $connection->expires_at instanceof CarbonInterface && $connection->expires_at->gt(now()->addMinutes(2))) {
            return $connection;
        }

        $payload = $this->refreshAccessToken($connection);

        $connection->forceFill([
            'token_type' => (string) Arr::get($payload, 'tokenType', 'bearer'),
            'access_token' => (string) Arr::get($payload, 'accessToken'),
            'refresh_token' => (string) Arr::get($payload, 'refreshToken', $connection->refresh_token),
            'expires_at' => now()->addSeconds((int) Arr::get($payload, 'expiresIn', 0)),
            'last_refreshed_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
        ])->save();

        return $connection->fresh();
    }

    private function tokenForConnection(CTraderConnection $connection): string
    {
        $token = (string) $connection->access_token;

        if ($token === '') {
            throw new RuntimeException('The cTrader connection is missing an access token.');
        }

        return $token;
    }

    /**
     * @param  callable(OpenApiClient):array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function withPlatformClient(string $environment, callable $callback, bool $retryLive = false): array
    {
        $client = new OpenApiClient($this->socketUri($environment), (int) config('services.ctrader.timeout', 15));

        try {
            $client->connect();

            return $callback($client);
        } catch (Throwable $exception) {
            if ($retryLive && strtolower($environment) === 'demo') {
                $client->disconnect();

                return $this->withPlatformClient('live', $callback, false);
            }

            throw $exception;
        } finally {
            $client->disconnect();
        }
    }

    private function applicationAuth(OpenApiClient $client): void
    {
        $client->request(
            PayloadType::APPLICATION_AUTH_REQ,
            [
                'clientId' => (string) config('services.ctrader.client_id'),
                'clientSecret' => (string) config('services.ctrader.client_secret'),
            ],
            [PayloadType::APPLICATION_AUTH_RES],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAuthorizedAccount(TradingAccount $account, ?CTraderConnection $connection): array
    {
        $authorizedAccounts = $connection instanceof CTraderConnection
            ? collect($this->getAuthorizedAccounts($connection))
            : collect();

        if ($authorizedAccounts->isEmpty()) {
            if (filled($account->platform_account_id)) {
                return [
                    'ctid_trader_account_id' => (string) $account->platform_account_id,
                    'trader_login' => $account->platform_login,
                    'environment' => $account->platform_environment ?: config('services.ctrader.environment', 'demo'),
                ];
            }

            throw new CTraderAuthorizationRequiredException('No authorized cTrader accounts were found. Complete the cTrader consent flow first.');
        }

        $matches = $authorizedAccounts;

        if (filled($account->platform_account_id)) {
            $match = $matches->first(fn (array $row): bool => (string) Arr::get($row, 'ctid_trader_account_id') === (string) $account->platform_account_id);

            if (is_array($match)) {
                return $match;
            }
        }

        if (filled($account->platform_login)) {
            $match = $matches->first(fn (array $row): bool => (string) Arr::get($row, 'trader_login') === (string) $account->platform_login);

            if (is_array($match)) {
                return $match;
            }
        }

        if (filled($account->platform_environment)) {
            $environmentMatches = $matches->where('environment', strtolower((string) $account->platform_environment))->values();

            if ($environmentMatches->count() === 1) {
                return $environmentMatches->first();
            }
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        throw new CTraderAuthorizationRequiredException('Multiple cTrader accounts are authorized. Select and link the correct account from the dashboard.');
    }

    private function autoLinkTradingAccounts(User $user, CTraderConnection $connection, array $authorizedAccounts): int
    {
        $accounts = collect($authorizedAccounts);
        $linked = 0;

        $tradingAccounts = $user->challengeTradingAccounts()
            ->where('platform_slug', 'ctrader')
            ->orderBy('created_at')
            ->get();

        foreach ($tradingAccounts as $tradingAccount) {
            $match = $accounts->first(function (array $authorizedAccount) use ($tradingAccount): bool {
                if (filled($tradingAccount->platform_account_id) && (string) Arr::get($authorizedAccount, 'ctid_trader_account_id') === (string) $tradingAccount->platform_account_id) {
                    return true;
                }

                if (filled($tradingAccount->platform_login) && (string) Arr::get($authorizedAccount, 'trader_login') === (string) $tradingAccount->platform_login) {
                    return true;
                }

                return false;
            });

            if (! is_array($match)) {
                continue;
            }

            $this->linkTradingAccount($tradingAccount, $connection, $match);
            $linked++;
        }

        $unlinked = $tradingAccounts->filter(fn (TradingAccount $account): bool => $account->ctrader_connection_id === null)->values();

        if ($unlinked->count() === 1 && $accounts->count() === 1) {
            $this->linkTradingAccount($unlinked->first(), $connection, $accounts->first());
            $linked++;
        }

        return $linked;
    }

    /**
     * @param  array<string, mixed>  $authorizedAccount
     */
    private function linkTradingAccount(TradingAccount $account, CTraderConnection $connection, array $authorizedAccount): void
    {
        $account->forceFill([
            'ctrader_connection_id' => $connection->id,
            'platform_account_id' => (string) Arr::get($authorizedAccount, 'ctid_trader_account_id'),
            'platform_login' => Arr::get($authorizedAccount, 'trader_login'),
            'platform_environment' => Arr::get($authorizedAccount, 'environment', $account->platform_environment ?: config('services.ctrader.environment', 'demo')),
            'platform_status' => 'authorized',
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeAccountInfo(array $payload, int $accountId, ?string $accountLogin): array
    {
        $trader = Arr::get($payload, 'trader.trader', []);
        $moneyDigits = (int) Arr::get($trader, 'moneyDigits', 2);
        $balance = $this->scaleMoney(Arr::get($trader, 'balance', 0), $moneyDigits);
        $pnlPayload = Arr::get($payload, 'pnl.positionUnrealizedPnL', []);
        $pnlDigits = (int) Arr::get($payload, 'pnl.moneyDigits', $moneyDigits);
        $equity = round($balance + collect($pnlPayload)->sum(fn (array $row): float => $this->scaleMoney(Arr::get($row, 'netUnrealizedPnL', 0), $pnlDigits)), 2);
        $traderLogin = $accountLogin ?? (Arr::has($trader, 'traderLogin') ? (string) Arr::get($trader, 'traderLogin') : null);

        return [
            'ctid_trader_account_id' => $accountId,
            'trader_login' => $traderLogin,
            'balance' => $balance,
            'equity' => $equity,
            'money_digits' => $moneyDigits,
            'broker_name' => Arr::get($trader, 'brokerName'),
            'account_type' => Arr::get($trader, 'accountType'),
            'deposit_asset_id' => Arr::get($trader, 'depositAssetId'),
            'leverage_in_cents' => Arr::get($trader, 'leverageInCents'),
            'registration_timestamp' => Arr::get($trader, 'registrationTimestamp'),
            'raw' => $trader,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function normalizeOpenPositions(array $payload): array
    {
        $pnlDigits = (int) Arr::get($payload, 'pnl.moneyDigits', 2);
        $pnlMap = collect(Arr::get($payload, 'pnl.positionUnrealizedPnL', []))
            ->filter(fn ($row): bool => is_array($row))
            ->keyBy(fn (array $row): string => (string) Arr::get($row, 'positionId'));

        return collect(Arr::get($payload, 'reconcile.position', []))
            ->filter(fn ($position): bool => is_array($position))
            ->map(function (array $position) use ($pnlMap, $pnlDigits): array {
                $positionId = (string) Arr::get($position, 'positionId');
                $pnl = $pnlMap->get($positionId, []);

                return [
                    'position_id' => $positionId,
                    'symbol_id' => Arr::get($position, 'tradeData.symbolId'),
                    'volume' => (int) Arr::get($position, 'tradeData.volume', 0),
                    'trade_side' => Arr::get($position, 'tradeData.tradeSide'),
                    'open_timestamp' => Arr::get($position, 'tradeData.openTimestamp'),
                    'price' => Arr::get($position, 'price'),
                    'net_unrealized_pnl' => $this->scaleMoney(Arr::get($pnl, 'netUnrealizedPnL', 0), $pnlDigits),
                    'gross_unrealized_pnl' => $this->scaleMoney(Arr::get($pnl, 'grossUnrealizedPnL', 0), $pnlDigits),
                    'raw' => $position,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function normalizeDeals(array $payload): array
    {
        return collect(Arr::get($payload, 'deals.deal', []))
            ->filter(fn ($deal): bool => is_array($deal))
            ->map(function (array $deal): array {
                $moneyDigits = (int) Arr::get($deal, 'moneyDigits', Arr::get($deal, 'closePositionDetail.moneyDigits', 2));

                return [
                    'deal_id' => Arr::get($deal, 'dealId'),
                    'order_id' => Arr::get($deal, 'orderId'),
                    'position_id' => Arr::get($deal, 'positionId'),
                    'symbol_id' => Arr::get($deal, 'symbolId'),
                    'trade_side' => Arr::get($deal, 'tradeSide'),
                    'execution_timestamp' => Arr::get($deal, 'executionTimestamp'),
                    'execution_price' => Arr::get($deal, 'executionPrice'),
                    'volume' => (int) Arr::get($deal, 'volume', 0),
                    'filled_volume' => (int) Arr::get($deal, 'filledVolume', 0),
                    'commission' => $this->scaleMoney(Arr::get($deal, 'commission', 0), $moneyDigits),
                    'net_profit' => $this->dealNetProfit($deal),
                    'balance_after_close' => $this->scaleMoney(Arr::get($deal, 'closePositionDetail.balance', 0), (int) Arr::get($deal, 'closePositionDetail.moneyDigits', $moneyDigits)),
                    'raw' => $deal,
                ];
            })
            ->values()
            ->all();
    }

    private function dealNetProfit(array $deal): float
    {
        $closeDetail = Arr::get($deal, 'closePositionDetail', []);
        $digits = (int) Arr::get($closeDetail, 'moneyDigits', Arr::get($deal, 'moneyDigits', 2));

        if (! is_array($closeDetail) || $closeDetail === []) {
            return 0.0;
        }

        $grossProfit = $this->scaleMoney(Arr::get($closeDetail, 'grossProfit', 0), $digits);
        $swap = $this->scaleMoney(Arr::get($closeDetail, 'swap', 0), $digits);
        $commission = $this->scaleMoney(Arr::get($closeDetail, 'commission', 0), $digits);
        $conversionFee = $this->scaleMoney(Arr::get($closeDetail, 'pnlConversionFee', 0), $digits);

        return round($grossProfit + $swap - $commission - $conversionFee, 2);
    }

    private function sumClosedProfitForDay(Collection $tradeHistory): float
    {
        $today = now()->startOfDay();

        return $tradeHistory
            ->filter(function (array $deal) use ($today): bool {
                $timestamp = Arr::get($deal, 'execution_timestamp');

                return is_numeric($timestamp)
                    && CarbonImmutable::createFromTimestampMs((int) $timestamp)->greaterThanOrEqualTo($today);
            })
            ->sum(fn (array $deal): float => (float) Arr::get($deal, 'net_profit', 0));
    }

    private function calculateDailyDrawdown(TradingAccount $account, float $currentEquity): float
    {
        $startOfDay = now()->startOfDay();
        $previousSnapshot = $account->balanceSnapshots()
            ->where('snapshot_at', '<', $startOfDay)
            ->latest('snapshot_at')
            ->first();

        $baseline = (float) ($previousSnapshot?->balance ?: $account->starting_balance ?: $account->account_size ?: $currentEquity);
        $todayMinimum = (float) ($account->balanceSnapshots()
            ->where('snapshot_at', '>=', $startOfDay)
            ->min('equity') ?: $currentEquity);

        $floor = min($todayMinimum, $currentEquity);

        return round(max($baseline - $floor, 0), 2);
    }

    private function calculateMaxDrawdown(TradingAccount $account, float $balance, float $equity): float
    {
        $startingBalance = (float) ($account->starting_balance ?: $account->account_size ?: $balance);
        $historicalMinEquity = (float) ($account->balanceSnapshots()->min('equity') ?: min($balance, $equity));
        $floor = min($historicalMinEquity, $balance, $equity);

        return round(max((float) $account->max_drawdown, max($startingBalance - $floor, 0)), 2);
    }

    private function countTradingDays(Collection $tradeHistory, Collection $openPositions, TradingAccount $account): int
    {
        $dates = $tradeHistory
            ->pluck('execution_timestamp')
            ->merge($openPositions->pluck('open_timestamp'))
            ->filter(fn ($timestamp): bool => is_numeric($timestamp))
            ->map(fn ($timestamp): string => CarbonImmutable::createFromTimestampMs((int) $timestamp)->toDateString())
            ->unique();

        return max((int) $account->trading_days_completed, $dates->count());
    }

    private function scaleMoney(int|float|string|null $value, int $digits): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round(((float) $value) / (10 ** $digits), 2);
    }

    private function phaseKey(TradingAccount $account): string
    {
        if ($account->is_funded) {
            return 'funded';
        }

        if ($account->challenge_type === 'one_step') {
            return 'single_phase';
        }

        return max((int) $account->phase_index, 1) > 1 ? 'phase_2' : 'phase_1';
    }

    private function stageLabel(TradingAccount $account): string
    {
        return match ($this->phaseKey($account)) {
            'single_phase' => 'Single Phase',
            'phase_2' => 'Challenge Step 2',
            'funded' => 'Funded',
            default => 'Challenge Step 1',
        };
    }

    private function historyFrom(TradingAccount $account): CarbonInterface
    {
        $fallback = now()->subDays((int) config('trading.platforms.ctrader.history_days', 90));

        if ($account->activated_at instanceof CarbonInterface) {
            $activatedAt = CarbonImmutable::instance($account->activated_at);

            return $activatedAt->greaterThan($fallback) ? $activatedAt : $fallback;
        }

        if ($account->created_at instanceof CarbonInterface) {
            $createdAt = CarbonImmutable::instance($account->created_at);

            return $createdAt->greaterThan($fallback) ? $createdAt : $fallback;
        }

        return $fallback;
    }

    private function redirectUri(): string
    {
        return (string) (config('services.ctrader.redirect_uri') ?: route('ctrader.auth.callback'));
    }

    private function socketUri(string $environment): string
    {
        $host = strtolower($environment) === 'live'
            ? (string) config('services.ctrader.transport.live_host', 'live.ctraderapi.com')
            : (string) config('services.ctrader.transport.demo_host', 'demo.ctraderapi.com');

        return sprintf(
            '%s://%s:%d',
            config('services.ctrader.transport.scheme', 'wss'),
            $host,
            (int) config('services.ctrader.transport.json_port', 5036),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logFailure(string $message, Throwable $exception, array $context = []): void
    {
        Log::channel('ctrader')->error($message, array_merge($context, [
            'exception' => $exception::class,
            'error' => $exception->getMessage(),
        ]));
    }
}
