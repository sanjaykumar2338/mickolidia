<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Admin\AdminChallengeActivationService;
use App\Services\Challenge\ChallengeLifecycleMailer;
use App\Services\TradingAccounts\TradeHistoryPanelBuilder;
use App\Support\CountryEligibility;
use App\Support\Mt5ConnectorStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminClientController extends Controller
{
    public function __construct(
        private readonly TradeHistoryPanelBuilder $tradeHistoryPanelBuilder,
        private readonly CountryEligibility $countryEligibility,
        private readonly Mt5ConnectorStatus $mt5ConnectorStatus,
    ) {}

    public function index(): View
    {
        $clients = User::query()
            ->with([
                'profile',
                'ctraderConnection',
                'latestChallengeTradingAccount.challengePlan',
                'latestTradingAccount.challengePlan',
                'latestOrder.paymentAttempts',
                'latestChallengePurchase.order',
            ])
            ->latest()
            ->get()
            ->map(fn (User $user): array => $this->clientTableRow($user));

        return view('admin.clients.index', [
            'clients' => $clients,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $user->loadMissing([
            'profile',
            'ctraderConnection',
            'challengeTradingAccounts.challengePlan',
            'latestChallengeTradingAccount.challengePlan',
            'latestTradingAccount.challengePlan',
            'latestOrder.paymentAttempts',
            'latestChallengePurchase.order',
        ]);

        $accounts = $this->availableAccountsForUser($user);
        $requestedAccountId = (int) $request->query('account', 0);
        $selectedAccount = $requestedAccountId > 0
            ? $accounts->firstWhere('id', $requestedAccountId)
            : null;
        $selectedAccount ??= $accounts->first();
        $latestOrder = $user->latestOrder;
        $latestSyncLog = $selectedAccount?->syncLogs()
            ->latest('id')
            ->first();
        $mt5SyncMeta = is_array($selectedAccount?->meta) ? (array) data_get($selectedAccount->meta, 'mt5_sync', []) : [];
        $failureContext = is_array($selectedAccount?->failure_context) ? $selectedAccount->failure_context : [];
        $mt5DeactivationCurrent = is_array($selectedAccount?->meta) ? (array) data_get($selectedAccount->meta, 'mt5_deactivation.current', []) : [];
        $connectorStatus = $this->mt5ConnectorStatus->forAccount($selectedAccount);

        return view('admin.clients.show', [
            'client' => [
                'id' => $user->id,
                'full_name' => $user->name,
                'email' => $user->email,
                'country' => $this->resolveCountry($user),
                'plan_selected' => $this->resolvePlanLabel($user),
                'payment_amount' => $this->resolvePaymentAmount($user),
                'payment_provider' => $this->resolvePaymentProvider($user),
                'payment_status' => $this->resolvePaymentStatus($user),
                'order_date' => $this->resolveOrderDate($user),
                'account_status' => $this->resolveAccountStatus($user),
                'account_status_key' => $this->resolveAccountStatusKey($user),
                'can_activate' => $this->canActivate($user),
            ],
            'accountOptions' => $accounts
                ->map(fn (TradingAccount $account): array => [
                    'id' => $account->id,
                    'reference' => $account->account_reference ?? 'Pending link',
                    'platform_login' => $account->platform_login ?? 'Link pending',
                    'phase' => $this->phaseLabel($account),
                    'status' => $this->humanizeStatus((string) ($account->challenge_status ?: $account->account_status ?: 'pending')),
                    'status_key' => (string) ($account->challenge_status ?: $account->account_status ?: 'pending'),
                    'url' => route('admin.clients.show', ['user' => $user, 'account' => $account->id]),
                    'is_selected' => (int) $account->id === (int) ($selectedAccount?->id ?? 0),
                ])
                ->all(),
            'metrics' => [
                [
                    'label' => __('site.admin.metrics.profit'),
                    'value' => $this->formatMoney((float) ($selectedAccount?->total_profit ?? 0)),
                ],
                [
                    'label' => 'Balance',
                    'value' => $this->formatMoney((float) ($selectedAccount?->balance ?? 0)),
                ],
                [
                    'label' => 'Equity',
                    'value' => $this->formatMoney((float) ($selectedAccount?->equity ?? 0)),
                ],
                [
                    'label' => __('site.admin.metrics.max_drawdown'),
                    'value' => number_format((float) ($selectedAccount?->drawdown_percent ?? 0), 1).'%',
                ],
                [
                    'label' => __('site.admin.metrics.trading_days'),
                    'value' => $selectedAccount !== null
                        ? sprintf(
                            '%d / %d',
                            (int) $selectedAccount->trading_days_completed,
                            (int) $selectedAccount->minimum_trading_days
                        )
                        : '0 / 0',
                ],
                [
                    'label' => __('site.admin.metrics.current_status'),
                    'value' => $this->resolveAccountStatus($user),
                ],
                [
                    'label' => 'Challenge Phase',
                    'value' => $selectedAccount !== null ? $this->phaseLabel($selectedAccount) : 'N/A',
                ],
                [
                    'label' => 'Failure Reason',
                    'value' => $selectedAccount?->failure_reason
                        ? $this->humanizeStatus((string) $selectedAccount->failure_reason)
                        : 'None',
                ],
                [
                    'label' => 'Sync Status',
                    'value' => $selectedAccount?->sync_status
                        ? $this->humanizeStatus((string) $selectedAccount->sync_status)
                        : 'Not synced',
                ],
                [
                    'label' => 'cTrader Auth',
                    'value' => $user->ctraderConnection?->last_authorized_at !== null ? 'Connected' : 'Pending',
                ],
            ],
            'selectedAccount' => $selectedAccount,
            'tradesPanel' => $this->tradeHistoryPanelBuilder->build($selectedAccount, [
                'empty_message' => __('Detailed trade rows will appear here after the selected account receives a synced snapshot with open positions or trade history.'),
                'available_message' => __('The latest persisted sync snapshot powers this admin trade review. Open and closed rows appear only when that snapshot includes them.'),
            ]),
            'billing' => [
                'full_name' => $latestOrder?->full_name ?? $user->name,
                'street_address' => $latestOrder?->street_address ?? $user->profile?->street_address ?? 'N/A',
                'city' => $latestOrder?->city ?? $user->profile?->city ?? 'N/A',
                'postal_code' => $latestOrder?->postal_code ?? $user->profile?->postal_code ?? 'N/A',
                'country' => $latestOrder instanceof Order
                    ? $this->countryName($latestOrder->country)
                    : $this->resolveCountry($user),
            ],
            'providerReferences' => [
                'order_number' => $latestOrder?->order_number ?? 'N/A',
                'checkout_id' => $latestOrder?->external_checkout_id ?? 'N/A',
                'payment_id' => $latestOrder?->external_payment_id ?? 'N/A',
                'customer_id' => $latestOrder?->external_customer_id ?? 'N/A',
                'platform_account_id' => $selectedAccount?->platform_account_id ?? 'Link pending',
                'platform_login' => $selectedAccount?->platform_login ?? 'Link pending',
                'platform_environment' => $selectedAccount?->platform_environment ?? 'N/A',
                'challenge_status' => $selectedAccount?->challenge_status ? $this->humanizeStatus((string) $selectedAccount->challenge_status) : 'N/A',
                'trial_status' => $selectedAccount?->trial_status ? $this->humanizeStatus((string) $selectedAccount->trial_status) : 'N/A',
                'account_status' => $selectedAccount?->account_status ? $this->humanizeStatus((string) $selectedAccount->account_status) : 'N/A',
                'connector_status' => $connectorStatus['label'],
                'connector_status_key' => $connectorStatus['status'],
                'connector_status_message' => $connectorStatus['message'],
                'connector_status_timeout_seconds' => $connectorStatus['timeout_seconds'],
                'stored_connector_status' => $mt5SyncMeta['status'] ?? 'N/A',
                'last_synced_at' => $this->formatDateTime($connectorStatus['last_sync_at'] ?? $selectedAccount?->last_synced_at),
                'last_evaluated_at' => $this->formatDateTime($selectedAccount?->last_evaluated_at),
                'sync_source' => $selectedAccount?->sync_source ? $this->humanizeStatus((string) $selectedAccount->sync_source) : 'N/A',
                'sync_error' => $selectedAccount?->sync_error ?? 'None',
                'breach_reason' => $selectedAccount?->failure_reason ? $this->humanizeStatus((string) $selectedAccount->failure_reason) : 'None',
                'breach_timestamp' => $this->formatDateTimeValue($failureContext['breach_timestamp'] ?? $selectedAccount?->failed_at),
                'breach_rule' => isset($failureContext['rule_breached']) ? $this->humanizeStatus((string) $failureContext['rule_breached']) : 'N/A',
                'breach_equity' => $this->formatMoneyValue($failureContext['equity_at_breach'] ?? null),
                'breach_balance' => $this->formatMoneyValue($failureContext['balance_at_breach'] ?? null),
                'disable_event' => $mt5DeactivationCurrent['event'] ?? 'N/A',
                'disable_status' => isset($mt5DeactivationCurrent['status']) ? $this->humanizeStatus((string) $mt5DeactivationCurrent['status']) : 'N/A',
                'disable_requested_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['requested_at'] ?? null),
                'disable_acknowledged_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['acknowledged_at'] ?? null),
                'disable_source' => $mt5DeactivationCurrent['source'] ?? 'N/A',
                'disable_error' => $mt5DeactivationCurrent['last_error'] ?? 'None',
                'last_ea_ping_at' => $this->formatDateTimeValue($mt5SyncMeta['last_ea_ping_at'] ?? null),
                'last_successful_metric_update_at' => $this->formatDateTimeValue($mt5SyncMeta['last_successful_metric_update_at'] ?? null),
                'last_sync_trigger' => $mt5SyncMeta['last_sync_trigger'] ?? 'N/A',
                'last_rejected_at' => $this->formatDateTimeValue($mt5SyncMeta['last_rejected_at'] ?? null),
                'last_rejected_reason' => $mt5SyncMeta['last_rejected_reason'] ?? 'None',
                'last_ignored_payload_at' => $this->formatDateTimeValue($mt5SyncMeta['last_ignored_payload_at'] ?? null),
                'last_ignored_reason' => $mt5SyncMeta['last_ignored_reason'] ?? 'None',
                'last_payload_summary' => is_array($mt5SyncMeta['last_payload_summary'] ?? null) ? $mt5SyncMeta['last_payload_summary'] : [],
                'latest_sync_log_status' => $latestSyncLog?->status ? $this->humanizeStatus((string) $latestSyncLog->status) : 'N/A',
                'latest_sync_log_message' => $latestSyncLog?->message ?? 'N/A',
                'latest_sync_log_error' => $latestSyncLog?->error_message ?? 'None',
                'latest_sync_log_completed_at' => $this->formatDateTime($latestSyncLog?->completed_at),
                'authorized_accounts_count' => is_array($user->ctraderConnection?->authorized_accounts) ? count($user->ctraderConnection->authorized_accounts) : 0,
                'last_authorized_at' => $this->formatDateTime($user->ctraderConnection?->last_authorized_at),
            ],
        ]);
    }

    public function metrics(Request $request, User $user): View
    {
        $user->loadMissing([
            'profile',
            'challengeTradingAccounts.challengePlan',
            'latestChallengeTradingAccount.challengePlan',
            'latestTradingAccount.challengePlan',
            'latestChallengePurchase.order',
        ]);

        $accounts = $this->availableAccountsForUser($user);
        $requestedAccountId = (int) $request->query('account', 0);
        $selectedAccount = $requestedAccountId > 0
            ? $accounts->firstWhere('id', $requestedAccountId)
            : null;
        $selectedAccount ??= $accounts->first();

        $connectorStatus = $this->mt5ConnectorStatus->forAccount($selectedAccount);
        $mt5SyncMeta = is_array($selectedAccount?->meta) ? (array) data_get($selectedAccount->meta, 'mt5_sync', []) : [];
        $mt5DeactivationCurrent = is_array($selectedAccount?->meta) ? (array) data_get($selectedAccount->meta, 'mt5_deactivation.current', []) : [];
        $latestSyncLog = $selectedAccount?->syncLogs()
            ->latest('id')
            ->first();
        $tradesPanel = $this->tradeHistoryPanelBuilder->build($selectedAccount, [
            'empty_message' => __('Detailed trade rows will appear here after this account receives synced MT5 open positions or trade history.'),
            'available_message' => __('This table is built from the latest persisted MT5 sync snapshots; no duplicate trade store is created.'),
        ]);
        $filters = $this->adminTradeFilters($request);
        $filteredRows = $this->filterAdminTradeRows($tradesPanel['rows'] ?? [], $filters);
        $tradeRows = $this->paginateAdminTradeRows($filteredRows, $request);

        return view('admin.clients.metrics', [
            'client' => [
                'id' => $user->id,
                'full_name' => $user->name,
                'email' => $user->email,
                'plan_selected' => $this->resolvePlanLabel($user),
            ],
            'accountOptions' => $accounts
                ->map(fn (TradingAccount $account): array => [
                    'id' => $account->id,
                    'reference' => $account->account_reference ?? 'Pending link',
                    'url' => route('admin.clients.metrics', ['user' => $user, 'account' => $account->id]),
                    'is_selected' => (int) $account->id === (int) ($selectedAccount?->id ?? 0),
                ])
                ->all(),
            'account' => [
                'reference' => $selectedAccount?->account_reference ?? 'N/A',
                'plan' => $selectedAccount instanceof TradingAccount
                    ? $this->challengeTypeLabel((string) $selectedAccount->challenge_type)
                    : $this->resolvePlanLabel($user),
                'phase' => $selectedAccount instanceof TradingAccount ? $this->phaseLabel($selectedAccount) : 'N/A',
                'status' => $selectedAccount?->account_status ? $this->humanizeStatus((string) $selectedAccount->account_status) : 'N/A',
                'challenge_status' => $selectedAccount?->challenge_status ? $this->humanizeStatus((string) $selectedAccount->challenge_status) : 'N/A',
                'connector_status' => $connectorStatus['label'],
                'connector_badge' => $connectorStatus['badge'],
                'last_ea_sync' => $this->formatDateTime($connectorStatus['last_heartbeat_at'] ?? $connectorStatus['last_sync_at'] ?? $selectedAccount?->last_synced_at),
                'balance' => $this->formatMoney((float) ($selectedAccount?->balance ?? 0)),
                'equity' => $this->formatMoney((float) ($selectedAccount?->equity ?? 0)),
                'floating_pl' => $this->formatMoney((float) ($selectedAccount?->profit_loss ?? 0)),
                'today_profit' => $this->formatMoney((float) ($selectedAccount?->today_profit ?? 0)),
                'total_realized_pl' => $this->formatMoney((float) ($selectedAccount?->total_profit ?? 0)),
                'trading_days' => $selectedAccount instanceof TradingAccount
                    ? sprintf('%d / %d', (int) $selectedAccount->trading_days_completed, (int) $selectedAccount->minimum_trading_days)
                    : '0 / 0',
                'open_positions_count' => (string) ($tradesPanel['summary']['open'] ?? data_get($mt5SyncMeta, 'last_payload_summary.positions_count', 0)),
                'closed_trades_count' => (string) ($tradesPanel['summary']['closed'] ?? data_get($mt5SyncMeta, 'last_payload_summary.closed_positions_count', 0)),
                'breach_status' => $selectedAccount?->failure_reason
                    ? __('Failed: :reason', ['reason' => $this->humanizeStatus((string) $selectedAccount->failure_reason)])
                    : __('None'),
            ],
            'filters' => $filters,
            'symbols' => collect($tradesPanel['rows'] ?? [])
                ->pluck('symbol')
                ->filter(fn (mixed $symbol): bool => is_string($symbol) && $symbol !== '' && $symbol !== '—')
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'tradeRows' => $tradeRows,
            'tradesSummary' => $tradesPanel['summary'] ?? ['open' => 0, 'closed' => 0, 'both' => 0],
            'tradesMessage' => $tradesPanel['message'] ?? __('No trade rows are available yet.'),
            'diagnostics' => [
                'latest_sync_log_status' => $latestSyncLog?->status ? $this->humanizeStatus((string) $latestSyncLog->status) : 'N/A',
                'latest_sync_log_message' => $latestSyncLog?->message ?? 'N/A',
                'latest_sync_log_error' => $latestSyncLog?->error_message ?? 'None',
                'latest_sync_log_completed_at' => $this->formatDateTime($latestSyncLog?->completed_at),
                'last_rejected_reason' => $mt5SyncMeta['last_rejected_reason'] ?? 'None',
                'last_ignored_reason' => $mt5SyncMeta['last_ignored_reason'] ?? 'None',
                'last_payload_summary' => is_array($mt5SyncMeta['last_payload_summary'] ?? null) ? $mt5SyncMeta['last_payload_summary'] : [],
                'disable_event' => $mt5DeactivationCurrent['event'] ?? 'N/A',
                'disable_status' => isset($mt5DeactivationCurrent['status']) ? $this->humanizeStatus((string) $mt5DeactivationCurrent['status']) : 'N/A',
                'disable_acknowledged_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['acknowledged_at'] ?? null),
                'disable_error' => $mt5DeactivationCurrent['last_error'] ?? 'None',
            ],
        ]);
    }

    public function activate(User $user, AdminChallengeActivationService $activationService): RedirectResponse
    {
        try {
            $account = $activationService->activate($user);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.clients.index')
                ->with('error', __('site.admin.clients.activation_error', [
                    'message' => $exception->getMessage(),
                ]));
        }

        return redirect()
            ->route('admin.clients.index')
            ->with('status', __('site.admin.clients.activation_success', [
                'name' => $user->name,
                'reference' => $account->account_reference,
            ]));
    }

    public function updateCredentials(Request $request, User $user, ChallengeLifecycleMailer $mailer): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'platform_login' => ['nullable', 'string', 'max:255'],
            'platform_account_id' => ['nullable', 'string', 'max:255'],
            'server_name' => ['nullable', 'string', 'max:255'],
            'trading_password' => ['nullable', 'string', 'max:255'],
            'investor_password' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var TradingAccount $account */
        $account = TradingAccount::query()
            ->where('user_id', $user->id)
            ->where('id', (int) $validated['account_id'])
            ->firstOrFail();

        $platformLogin = trim((string) ($validated['platform_login'] ?? ''));
        $platformAccountId = trim((string) ($validated['platform_account_id'] ?? ''));

        if ($platformLogin === '' && $platformAccountId !== '') {
            $platformLogin = $platformAccountId;
        }

        if ($platformAccountId === '' && $platformLogin !== '') {
            $platformAccountId = $platformLogin;
        }

        $meta = $account->meta ?? [];
        $credentials = is_array(Arr::get($meta, 'credentials')) ? Arr::get($meta, 'credentials') : [];

        $serverName = trim((string) ($validated['server_name'] ?? ''));
        $tradingPassword = trim((string) ($validated['trading_password'] ?? ''));
        $investorPassword = trim((string) ($validated['investor_password'] ?? ''));

        if ($serverName !== '') {
            $credentials['server'] = $serverName;
            $credentials['mt5_server'] = $serverName;
            $meta['mt5_server'] = $serverName;
        }

        if ($tradingPassword !== '') {
            $credentials['password'] = $tradingPassword;
            $credentials['trading_password'] = $tradingPassword;
        }

        if ($investorPassword !== '') {
            $credentials['investor_password'] = $investorPassword;
            $credentials['readonly_password'] = $investorPassword;
        }

        if ($serverName !== '' || $tradingPassword !== '' || $investorPassword !== '') {
            $credentials['last_updated_at'] = now()->toIso8601String();
            $meta['credentials'] = $credentials;
        }

        $account->forceFill(array_filter([
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => $platformLogin !== '' ? $platformLogin : null,
            'platform_account_id' => $platformAccountId !== '' ? $platformAccountId : null,
            'meta' => $meta,
        ], static fn ($value) => $value !== null))->save();

        $freshAccount = $account->fresh(['user', 'order', 'challengePlan', 'challengePurchase']) ?? $account;
        $emailWasSent = $mailer->sendPurchaseCredentialsIfNeeded($freshAccount);

        $status = $freshAccount->fresh()?->challenge_purchase_email_sent_at !== null
            ? __('MT5 credentials were saved. The credential email has already been sent for this account.')
            : __('MT5 credentials were saved. The credential email will send once the login, server, and trading password are all available.');

        if ($emailWasSent) {
            $status = __('MT5 credentials were saved and the purchase credential email was sent.');
        }

        return redirect()
            ->route('admin.clients.show', ['user' => $user, 'account' => $account->id])
            ->with('status', $status);
    }

    /**
     * @return array{status:string,symbol:string,date_from:string,date_to:string}
     */
    private function adminTradeFilters(Request $request): array
    {
        $status = strtolower((string) $request->query('trade_status', 'both'));

        return [
            'status' => in_array($status, ['both', 'open', 'closed'], true) ? $status : 'both',
            'symbol' => trim((string) $request->query('symbol', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array{status:string,symbol:string,date_from:string,date_to:string}  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filterAdminTradeRows(array $rows, array $filters): array
    {
        $from = $this->parseFilterDate($filters['date_from'], endOfDay: false);
        $to = $this->parseFilterDate($filters['date_to'], endOfDay: true);

        return collect($rows)
            ->filter(function (array $row) use ($filters, $from, $to): bool {
                if ($filters['status'] !== 'both' && ($row['filter'] ?? null) !== $filters['status']) {
                    return false;
                }

                if ($filters['symbol'] !== '' && strcasecmp((string) ($row['symbol'] ?? ''), $filters['symbol']) !== 0) {
                    return false;
                }

                $tradeAt = $this->parseFilterDate((string) (($row['close_at'] ?? null) ?: ($row['open_at'] ?? '')), endOfDay: false);

                if ($from instanceof Carbon && (! $tradeAt instanceof Carbon || $tradeAt->lt($from))) {
                    return false;
                }

                if ($to instanceof Carbon && (! $tradeAt instanceof Carbon || $tradeAt->gt($to))) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateAdminTradeRows(array $rows, Request $request): LengthAwarePaginator
    {
        $perPage = min(max((int) $request->query('per_page', 25), 10), 100);
        $page = max((int) $request->query('page', 1), 1);
        $items = collect($rows)->forPage($page, $perPage)->values();

        return (new LengthAwarePaginator(
            $items,
            count($rows),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        ));
    }

    private function parseFilterDate(string $value, bool $endOfDay): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function clientTableRow(User $user): array
    {
        $currentAccount = $this->currentTradingAccount($user);

        return [
            'id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'country' => $this->resolveCountry($user),
            'plan_selected' => $this->resolvePlanLabel($user),
            'payment_amount' => $this->resolvePaymentAmount($user),
            'payment_provider' => $this->resolvePaymentProvider($user),
            'payment_status' => $this->resolvePaymentStatus($user),
            'order_date' => $this->resolveOrderDate($user),
            'account_status' => $this->resolveAccountStatus($user),
            'account_status_key' => $this->resolveAccountStatusKey($user),
            'account_reference' => $currentAccount?->account_reference,
            'can_activate' => $this->canActivate($user),
        ];
    }

    private function resolvePlanLabel(User $user): string
    {
        $purchase = $user->latestChallengePurchase;

        if ($purchase !== null) {
            return sprintf(
                '%s / %dK',
                $this->challengeTypeLabel($purchase->challenge_type),
                (int) ($purchase->account_size / 1000),
            );
        }

        $order = $user->latestOrder;

        if ($order !== null) {
            return sprintf(
                '%s / %dK',
                $this->challengeTypeLabel($order->challenge_type),
                (int) ($order->account_size / 1000),
            );
        }

        if ($user->plan_type !== null && $user->account_size !== null) {
            return sprintf('%s / %dK', $user->plan_type, (int) ($user->account_size / 1000));
        }

        $plan = $this->currentTradingAccount($user)?->challengePlan;

        return $plan?->name ?? 'Not assigned';
    }

    private function resolvePaymentAmount(User $user): string
    {
        if ($user->latestOrder instanceof Order) {
            return $this->formatMoney((float) $user->latestOrder->final_price, $user->latestOrder->currency);
        }

        $amount = $user->payment_amount;

        if ($amount === null) {
            $amount = $this->currentTradingAccount($user)?->challengePlan?->entry_fee;
        }

        return $amount !== null
            ? $this->formatMoney((float) $amount)
            : '$0.00';
    }

    private function resolvePaymentProvider(User $user): string
    {
        return $user->latestOrder?->payment_provider !== null
            ? ucfirst((string) $user->latestOrder->payment_provider)
            : 'N/A';
    }

    private function resolvePaymentStatus(User $user): string
    {
        return $user->latestOrder?->payment_status !== null
            ? ucfirst((string) $user->latestOrder->payment_status)
            : 'N/A';
    }

    private function resolveOrderDate(User $user): string
    {
        return $user->latestOrder?->created_at?->format('Y-m-d H:i') ?? 'N/A';
    }

    private function resolveAccountStatus(User $user): string
    {
        return $this->humanizeStatus($this->resolveAccountStatusKey($user));
    }

    private function resolveAccountStatusKey(User $user): string
    {
        $account = $this->currentTradingAccount($user);
        $purchaseStatus = $user->latestChallengePurchase?->account_status;
        $challengeStatus = (string) ($account?->challenge_status ?? '');

        if (in_array($challengeStatus, ['passed', 'failed'], true)) {
            return $challengeStatus;
        }

        if ($account?->account_status !== null && ($account->account_status !== 'pending_activation' || $purchaseStatus === null)) {
            return (string) $account->account_status;
        }

        if ($purchaseStatus !== null) {
            return (string) $purchaseStatus;
        }

        $status = $user->status;

        if (($status === null || strtolower((string) $status) === 'active') && $account?->status !== null) {
            $status = $account->status;
        }

        $status ??= 'active';

        return strtolower((string) $status);
    }

    private function canActivate(User $user): bool
    {
        return $this->resolveAccountStatusKey($user) === 'pending_activation';
    }

    private function resolveCountry(User $user): string
    {
        if ($user->profile?->country) {
            return $user->profile->country;
        }

        if ($user->latestOrder?->country) {
            return $this->countryName($user->latestOrder->country);
        }

        return 'N/A';
    }

    private function currentTradingAccount(User $user): ?TradingAccount
    {
        return $user->latestChallengeTradingAccount ?? $user->latestTradingAccount;
    }

    private function availableAccountsForUser(User $user)
    {
        $accounts = $user->challengeTradingAccounts
            ->sortByDesc('created_at')
            ->values();

        if ($accounts->isNotEmpty()) {
            return $accounts;
        }

        $fallbackAccount = $this->currentTradingAccount($user);

        return $fallbackAccount instanceof TradingAccount
            ? collect([$fallbackAccount])
            : collect();
    }

    private function challengeTypeLabel(string $challengeType): string
    {
        return (string) config(
            'wolforix.challenge_catalog.'.$challengeType.'.label',
            $challengeType === 'one_step' ? '1-Step Instant' : '2-Step Pro',
        );
    }

    private function phaseLabel(TradingAccount $account): string
    {
        return match (true) {
            $account->challenge_type === 'one_step' => 'Single Phase',
            (int) $account->phase_index > 1 => 'Phase 2',
            default => 'Phase 1',
        };
    }

    private function countryName(string $countryCode): string
    {
        return $this->countryEligibility->countryName($countryCode);
    }

    private function formatMoney(float $amount, string $currency = 'USD'): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€'.number_format($amount, 2),
            'GBP' => '£'.number_format($amount, 2),
            default => '$'.number_format($amount, 2),
        };
    }

    private function formatMoneyValue(mixed $amount, string $currency = 'USD'): string
    {
        if (! is_numeric($amount)) {
            return 'N/A';
        }

        return $this->formatMoney((float) $amount, $currency);
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return 'Not synced yet';
    }

    private function formatDateTimeValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_string($value) && $value !== '') {
            try {
                return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
            } catch (\Throwable) {
                return $value;
            }
        }

        return 'N/A';
    }

    private function humanizeStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }
}
