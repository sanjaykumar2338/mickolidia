<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\TradingAccountBalanceSnapshot;
use App\Models\User;
use App\Services\Admin\AdminChallengeActivationService;
use App\Services\Challenge\ChallengeLifecycleMailer;
use App\Services\TradingAccounts\TradeHistoryPanelBuilder;
use App\Support\ChallengeCalculationBreakdown;
use App\Support\CountryEligibility;
use App\Support\Mt5ConnectorCredentials;
use App\Support\Mt5ConnectorPackageBuilder;
use App\Support\Mt5ConnectorStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminClientController extends Controller
{
    private const PHASE1_VALIDATED_METAAPI_LOGINS = [
        '340134',
        '335400',
    ];

    public function __construct(
        private readonly TradeHistoryPanelBuilder $tradeHistoryPanelBuilder,
        private readonly CountryEligibility $countryEligibility,
        private readonly Mt5ConnectorStatus $mt5ConnectorStatus,
        private readonly ChallengeCalculationBreakdown $challengeCalculationBreakdown,
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
            'metaApiSummary' => $this->metaApiSummary(),
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
        $latestSnapshot = $this->latestAdminMetricSnapshot($selectedAccount);
        $calculation = $selectedAccount instanceof TradingAccount
            ? $this->challengeCalculationBreakdown->forAccount($selectedAccount, $latestSnapshot)
            : [];
        $accountMetrics = $this->formatSelectedAccountMetrics($calculation, $selectedAccount);
        $selectedLifecycleState = $selectedAccount instanceof TradingAccount
            ? (string) data_get($selectedAccount->meta, 'metaapi_lifecycle.state', 'waiting_for_first_sync')
            : 'N/A';
        $selectedSyncHealth = $selectedAccount instanceof TradingAccount
            ? (string) data_get($selectedAccount->meta, 'metaapi_lifecycle.sync_health', $connectorStatus['status'] ?? 'not_connected')
            : 'N/A';
        $selectedOnboardingState = $selectedAccount instanceof TradingAccount
            ? (string) data_get($selectedAccount->meta, 'metaapi_onboarding.state', 'pending')
            : 'N/A';
        $selectedReadyToTrade = $selectedAccount instanceof TradingAccount && $this->metaApiReadyToTrade($selectedAccount);
        $selectedPhaseOneReady = $selectedAccount instanceof TradingAccount && $this->metaApiPhaseOneReady($selectedAccount);

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
                    'value' => $accountMetrics['realized_profit'],
                ],
                [
                    'label' => 'Challenge Balance',
                    'value' => $accountMetrics['challenge_balance'],
                ],
                [
                    'label' => 'Challenge Equity',
                    'value' => $accountMetrics['challenge_equity'],
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
                    'label' => 'Dashboard Source',
                    'value' => $selectedAccount?->sync_source
                        ? $this->sourceLabel((string) $selectedAccount->sync_source)
                        : 'N/A',
                ],
                [
                    'label' => 'Ready To Trade',
                    'value' => $selectedReadyToTrade ? 'Yes' : 'No',
                ],
                [
                    'label' => 'cTrader Auth',
                    'value' => $user->ctraderConnection?->last_authorized_at !== null ? 'Connected' : 'Pending',
                ],
            ],
            'selectedAccount' => $selectedAccount,
            'selectedAccountMetrics' => $accountMetrics,
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
                'sync_source' => $selectedAccount?->sync_source ? $this->sourceLabel((string) $selectedAccount->sync_source) : 'N/A',
                'sync_error' => $selectedAccount?->sync_error ?? 'None',
                'breach_reason' => $selectedAccount?->failure_reason ? $this->humanizeStatus((string) $selectedAccount->failure_reason) : 'None',
                'breach_detected_at' => $this->formatDateTimeValue($failureContext['breach_timestamp'] ?? $selectedAccount?->failed_at),
                'breach_timestamp' => $this->formatDateTimeValue($failureContext['breach_timestamp'] ?? $selectedAccount?->failed_at),
                'breach_rule' => isset($failureContext['rule_breached']) ? $this->humanizeStatus((string) $failureContext['rule_breached']) : 'N/A',
                'breach_equity' => $this->formatMoneyValue($failureContext['equity_at_breach'] ?? null),
                'breach_balance' => $this->formatMoneyValue($failureContext['balance_at_breach'] ?? null),
                'disable_event' => $mt5DeactivationCurrent['event'] ?? 'N/A',
                'disable_status' => isset($mt5DeactivationCurrent['status']) ? $this->humanizeStatus((string) $mt5DeactivationCurrent['status']) : 'N/A',
                'disable_requested_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['requested_at'] ?? null),
                'disable_last_attempt_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['last_attempt_at'] ?? null),
                'disable_attempts' => isset($mt5DeactivationCurrent['attempts']) ? (string) $mt5DeactivationCurrent['attempts'] : 'N/A',
                'disable_executed_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['executed_at'] ?? null),
                'disable_acknowledged_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['acknowledged_at'] ?? null),
                'disable_source' => $mt5DeactivationCurrent['source'] ?? 'N/A',
                'disable_bridge_status' => isset($mt5DeactivationCurrent['bridge_status']) ? (string) $mt5DeactivationCurrent['bridge_status'] : 'N/A',
                'disable_response_payload' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['bridge_response'] ?? null),
                'disable_error' => $mt5DeactivationCurrent['last_error'] ?? 'None',
                'disable_failure_reason' => $mt5DeactivationCurrent['last_error'] ?? 'None',
                'mt5_trading_permission_state' => $mt5DeactivationCurrent['trading_permission_state'] ?? 'Unknown',
                'mt5_trading_permission_payload' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['trading_permission_payload'] ?? null),
                'close_status' => isset($mt5DeactivationCurrent['close_status']) ? $this->humanizeStatus((string) $mt5DeactivationCurrent['close_status']) : 'N/A',
                'close_success' => array_key_exists('close_success', $mt5DeactivationCurrent) ? ((bool) $mt5DeactivationCurrent['close_success'] ? 'Yes' : 'No') : 'N/A',
                'closed_positions_count' => isset($mt5DeactivationCurrent['closed_positions_count']) ? (string) $mt5DeactivationCurrent['closed_positions_count'] : 'N/A',
                'positions_remaining_count' => isset($mt5DeactivationCurrent['positions_remaining_count']) ? (string) $mt5DeactivationCurrent['positions_remaining_count'] : 'N/A',
                'closed_position_tickets' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['closed_position_tickets'] ?? null),
                'closed_position_identifiers' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['closed_position_identifiers'] ?? null),
                'failed_close_tickets' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['failed_close_tickets'] ?? null),
                'close_failed_reasons' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['close_failed_reasons'] ?? null),
                'close_result_message' => $mt5DeactivationCurrent['close_result_message'] ?? 'None',
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
                'lifecycle_state' => $selectedLifecycleState !== 'N/A' ? $this->humanizeStatus($selectedLifecycleState) : 'N/A',
                'sync_health' => $selectedSyncHealth !== 'N/A' ? $this->humanizeStatus($selectedSyncHealth) : 'N/A',
                'onboarding_state' => $selectedOnboardingState !== 'N/A' ? $this->humanizeStatus($selectedOnboardingState) : 'N/A',
                'ready_to_trade' => $selectedReadyToTrade ? 'Yes' : 'No',
                'phase_1_ready' => $selectedPhaseOneReady ? 'Yes' : 'No',
                'phase_2_ready' => $selectedReadyToTrade ? 'Yes' : 'No',
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
        $latestSnapshot = $this->latestAdminMetricSnapshot($selectedAccount);
        $calculation = $selectedAccount instanceof TradingAccount
            ? $this->challengeCalculationBreakdown->forAccount($selectedAccount, $latestSnapshot)
            : [];
        $selectedReadyToTrade = $selectedAccount instanceof TradingAccount && $this->metaApiReadyToTrade($selectedAccount);
        $selectedPhaseOneReady = $selectedAccount instanceof TradingAccount && $this->metaApiPhaseOneReady($selectedAccount);
        $tradeRowsForSummary = $tradesPanel['rows'] ?? [];
        $todaySummary = $this->adminTodayTradeSummary($selectedAccount, $latestSnapshot, $tradeRowsForSummary, $connectorStatus);
        $filters = $this->adminTradeFilters($request);
        $filteredRows = $this->filterAdminTradeRows($tradeRowsForSummary, $filters);
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
                'connector_is_stale' => (bool) $connectorStatus['is_stale'],
                'lifecycle_state' => $selectedAccount instanceof TradingAccount
                    ? $this->humanizeStatus((string) data_get($selectedAccount->meta, 'metaapi_lifecycle.state', 'waiting_for_first_sync'))
                    : 'N/A',
                'sync_health' => $selectedAccount instanceof TradingAccount
                    ? $this->humanizeStatus((string) data_get($selectedAccount->meta, 'metaapi_lifecycle.sync_health', $connectorStatus['status'] ?? 'not_connected'))
                    : 'N/A',
                'onboarding_state' => $selectedAccount instanceof TradingAccount
                    ? $this->humanizeStatus((string) data_get($selectedAccount->meta, 'metaapi_onboarding.state', 'pending'))
                    : 'N/A',
                'ready_to_trade' => $selectedReadyToTrade ? 'Yes' : 'No',
                'phase_1_ready' => $selectedPhaseOneReady ? 'Yes' : 'No',
                'phase_2_ready' => $selectedReadyToTrade ? 'Yes' : 'No',
                'sync_source' => $selectedAccount?->sync_source ? $this->sourceLabel((string) $selectedAccount->sync_source) : 'N/A',
                'connector_download_url' => $selectedAccount instanceof TradingAccount && $this->isMt5Account($selectedAccount)
                    ? route('admin.clients.mt5-connector.download', ['user' => $user, 'account' => $selectedAccount])
                    : null,
                'last_ea_sync' => $this->formatDateTime($connectorStatus['last_heartbeat_at'] ?? $connectorStatus['last_sync_at'] ?? $selectedAccount?->last_synced_at),
                'balance' => $this->formatMoney((float) ($calculation['challenge_balance'] ?? 0)),
                'equity' => $this->formatMoney((float) ($calculation['challenge_equity'] ?? 0)),
                'floating_pl' => $this->formatMoney((float) ($calculation['floating_pnl'] ?? $todaySummary['current_floating_pnl_value'])),
                'snapshot_pl' => $this->formatMoney($this->adminMetricAmount($latestSnapshot?->profit_loss, $selectedAccount?->profit_loss)),
                'today_profit' => $todaySummary['today_profit_loss'],
                'total_realized_pl' => $this->formatMoney((float) ($calculation['realized_profit'] ?? $this->adminMetricAmount($latestSnapshot?->total_profit, $selectedAccount?->total_profit))),
                'profit_target_progress' => number_format((float) ($calculation['profit_target_progress_percent'] ?? 0), 1).'%',
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
            'todaySummary' => $todaySummary,
            'calculation' => $this->formatAdminCalculationBreakdown($calculation),
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
                'disable_requested_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['requested_at'] ?? null),
                'disable_last_attempt_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['last_attempt_at'] ?? null),
                'disable_attempts' => isset($mt5DeactivationCurrent['attempts']) ? (string) $mt5DeactivationCurrent['attempts'] : 'N/A',
                'disable_executed_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['executed_at'] ?? null),
                'disable_acknowledged_at' => $this->formatDateTimeValue($mt5DeactivationCurrent['acknowledged_at'] ?? null),
                'disable_source' => $mt5DeactivationCurrent['source'] ?? 'N/A',
                'disable_bridge_status' => isset($mt5DeactivationCurrent['bridge_status']) ? (string) $mt5DeactivationCurrent['bridge_status'] : 'N/A',
                'disable_response_payload' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['bridge_response'] ?? null),
                'disable_error' => $mt5DeactivationCurrent['last_error'] ?? 'None',
                'disable_failure_reason' => $mt5DeactivationCurrent['last_error'] ?? 'None',
                'mt5_trading_permission_state' => $mt5DeactivationCurrent['trading_permission_state'] ?? 'Unknown',
                'mt5_trading_permission_payload' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['trading_permission_payload'] ?? null),
                'close_status' => isset($mt5DeactivationCurrent['close_status']) ? $this->humanizeStatus((string) $mt5DeactivationCurrent['close_status']) : 'N/A',
                'close_success' => array_key_exists('close_success', $mt5DeactivationCurrent) ? ((bool) $mt5DeactivationCurrent['close_success'] ? 'Yes' : 'No') : 'N/A',
                'closed_positions_count' => isset($mt5DeactivationCurrent['closed_positions_count']) ? (string) $mt5DeactivationCurrent['closed_positions_count'] : 'N/A',
                'positions_remaining_count' => isset($mt5DeactivationCurrent['positions_remaining_count']) ? (string) $mt5DeactivationCurrent['positions_remaining_count'] : 'N/A',
                'closed_position_tickets' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['closed_position_tickets'] ?? null),
                'closed_position_identifiers' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['closed_position_identifiers'] ?? null),
                'failed_close_tickets' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['failed_close_tickets'] ?? null),
                'close_failed_reasons' => $this->formatDiagnosticPayload($mt5DeactivationCurrent['close_failed_reasons'] ?? null),
                'close_result_message' => $mt5DeactivationCurrent['close_result_message'] ?? 'None',
            ],
        ]);
    }

    public function downloadMt5Connector(
        User $user,
        TradingAccount $account,
        Mt5ConnectorCredentials $connectorCredentials,
        Mt5ConnectorPackageBuilder $packageBuilder,
    ): BinaryFileResponse {
        abort_unless((int) $account->user_id === (int) $user->id, 404);
        abort_unless($this->isMt5Account($account), 404);

        $connector = $connectorCredentials->forAccount($account);
        $package = $packageBuilder->build($account->fresh() ?? $account, $connector);

        return response()
            ->download($package['path'], $package['filename'], [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ])
            ->deleteFileAfterSend(true);
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

    private function latestAdminMetricSnapshot(?TradingAccount $account): ?TradingAccountBalanceSnapshot
    {
        if (! $account instanceof TradingAccount) {
            return null;
        }

        /** @var TradingAccountBalanceSnapshot|null $snapshot */
        $snapshot = $account->balanceSnapshots()
            ->orderByDesc('snapshot_at')
            ->orderByDesc('id')
            ->first([
                'id',
                'trading_account_id',
                'snapshot_at',
                'balance',
                'equity',
                'profit_loss',
                'total_profit',
                'today_profit',
                'payload',
            ]);

        return $snapshot;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $connectorStatus
     * @return array<string, mixed>
     */
    private function adminTodayTradeSummary(
        ?TradingAccount $account,
        ?TradingAccountBalanceSnapshot $latestSnapshot,
        array $rows,
        array $connectorStatus,
    ): array {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $rowCollection = collect($rows);
        $closedToday = $rowCollection->filter(fn (array $row): bool => ($row['filter'] ?? null) === 'closed'
            && $this->tradeRowFallsBetween($row, $todayStart, $todayEnd, preferCloseTime: true));
        $openToday = $rowCollection->filter(fn (array $row): bool => ($row['filter'] ?? null) === 'open'
            && $this->tradeRowFallsBetween($row, $todayStart, $todayEnd, preferCloseTime: false));
        $currentOpenRows = $rowCollection->filter(fn (array $row): bool => ($row['filter'] ?? null) === 'open');

        $todayProfitValue = $this->snapshotPayloadHasTodayProfit($latestSnapshot)
            ? $this->adminMetricAmount($latestSnapshot?->today_profit, $account?->today_profit)
            : null;
        $todayProfitSource = __('Latest MT5 payload');

        if ($todayProfitValue === null) {
            $todayProfitValue = round($closedToday->sum(
                fn (array $row): float => (float) ($row['net_result_value'] ?? $row['profit_value'] ?? 0)
            ), 2);
            $todayProfitSource = __('Calculated from today’s closed trades');
        }

        $openRowsWithProfit = $currentOpenRows->filter(
            fn (array $row): bool => is_numeric($row['profit_value'] ?? null)
        );

        if ($openRowsWithProfit->isNotEmpty()) {
            $currentFloatingPnl = round($openRowsWithProfit->sum(fn (array $row): float => (float) $row['profit_value']), 2);
        } else {
            $currentFloatingPnl = $this->payloadNumericValue($latestSnapshot?->payload, [
                'open_profit',
                'openProfit',
                'floating_profit',
                'floatingProfit',
                'floating_pnl',
                'floatingPnl',
                'current_floating_pnl',
                'currentFloatingPnl',
                'raw.open_profit',
                'raw.openProfit',
                'raw.floating_profit',
                'raw.floatingProfit',
            ]);

            if ($currentFloatingPnl === null) {
                $equity = $this->adminMetricAmount($latestSnapshot?->equity, $account?->equity);
                $balance = $this->adminMetricAmount($latestSnapshot?->balance, $account?->balance);
                $currentFloatingPnl = round($equity - $balance, 2);
            }
        }

        $lastSyncedAt = $connectorStatus['last_heartbeat_at']
            ?? $connectorStatus['last_sync_at']
            ?? $latestSnapshot?->snapshot_at
            ?? $account?->last_synced_at;

        return [
            'today_profit_loss' => $this->formatMoney($todayProfitValue),
            'today_profit_loss_value' => $todayProfitValue,
            'today_profit_source' => $todayProfitSource,
            'today_closed_trades_count' => $closedToday->count(),
            'today_open_trades_count' => $openToday->count(),
            'current_open_positions_count' => $currentOpenRows->count(),
            'current_floating_pnl' => $this->formatMoney($currentFloatingPnl),
            'current_floating_pnl_value' => $currentFloatingPnl,
            'last_synced_at' => $this->formatDateTimeValue($lastSyncedAt),
            'snapshot_at' => $this->formatDateTime($latestSnapshot?->snapshot_at),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function tradeRowFallsBetween(array $row, Carbon $from, Carbon $to, bool $preferCloseTime): bool
    {
        $timestamp = $this->parseFilterDate((string) (($preferCloseTime ? ($row['close_at'] ?? null) : null) ?: ($row['open_at'] ?? '')), endOfDay: false);

        return $timestamp instanceof Carbon
            && $timestamp->betweenIncluded($from, $to);
    }

    private function snapshotPayloadHasTodayProfit(?TradingAccountBalanceSnapshot $snapshot): bool
    {
        return $this->payloadHasFilledValue($snapshot?->payload, [
            'today_profit',
            'todayProfit',
            'today_pnl',
            'todayPnl',
            'today_realized_pnl',
            'todayRealizedPnl',
            'raw.today_profit',
            'raw.todayProfit',
            'raw.today_pnl',
            'raw.todayPnl',
            'mt5.today_profit',
            'mt5.todayProfit',
        ]);
    }

    /**
     * @param  list<string>  $paths
     */
    private function payloadHasFilledValue(mixed $payload, array $paths): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        foreach ($paths as $path) {
            if (! blank(Arr::get($payload, $path))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $paths
     */
    private function payloadNumericValue(mixed $payload, array $paths): ?float
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach ($paths as $path) {
            $value = Arr::get($payload, $path);

            if (is_numeric($value)) {
                return round((float) $value, 2);
            }
        }

        return null;
    }

    private function adminMetricAmount(mixed $preferred, mixed $fallback = null): float
    {
        if (is_numeric($preferred)) {
            return (float) $preferred;
        }

        if (is_numeric($fallback)) {
            return (float) $fallback;
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array{cards:list<array{label:string,value:string}>,formulas:array<string,string>}
     */
    private function formatAdminCalculationBreakdown(array $calculation): array
    {
        if ($calculation === []) {
            return [
                'cards' => [],
                'formulas' => [],
            ];
        }

        return [
            'cards' => [
                ['label' => 'Challenge baseline', 'value' => $this->formatMoney((float) $calculation['challenge_starting_balance']).' · '.$calculation['challenge_starting_balance_source']],
                ['label' => 'Broker reference', 'value' => $this->formatMoney((float) $calculation['broker_phase_reference_balance']).' · '.$calculation['broker_reference_source']],
                ['label' => 'Raw MT5 Broker Balance', 'value' => $this->formatMoney((float) $calculation['raw_balance'])],
                ['label' => 'Raw MT5 Broker Equity', 'value' => $this->formatMoney((float) $calculation['raw_equity'])],
                ['label' => 'Challenge balance / equity', 'value' => $this->formatMoney((float) $calculation['challenge_balance']).' / '.$this->formatMoney((float) $calculation['challenge_equity'])],
                ['label' => 'Realized P/L', 'value' => $this->formatMoney((float) $calculation['realized_profit'])],
                ['label' => 'Today P/L', 'value' => $this->formatMoney((float) $calculation['today_profit'])],
                ['label' => 'Profit target', 'value' => $this->formatMoney((float) $calculation['profit_target_amount']).' · '.number_format((float) $calculation['profit_target_progress_percent'], 1).'%'],
                ['label' => 'Profit target met', 'value' => (bool) $calculation['profit_target_met'] ? 'Yes' : 'No'],
                ['label' => 'Daily loss', 'value' => $this->formatMoney((float) $calculation['daily_loss_used']).' / '.$this->formatMoney((float) $calculation['daily_loss_limit'])],
                ['label' => 'Daily breach', 'value' => (bool) $calculation['daily_breach'] ? 'Yes' : 'No'],
                ['label' => 'Max drawdown', 'value' => $this->formatMoney((float) $calculation['max_drawdown_used']).' / '.$this->formatMoney((float) $calculation['max_drawdown_limit'])],
                ['label' => 'Max breach', 'value' => (bool) $calculation['max_breach'] ? 'Yes' : 'No'],
            ],
            'formulas' => (array) ($calculation['formula'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $calculation
     * @return array<string, string>
     */
    private function formatSelectedAccountMetrics(array $calculation, ?TradingAccount $account): array
    {
        if ($calculation !== []) {
            return [
                'challenge_balance' => $this->formatMoney((float) $calculation['challenge_balance']),
                'challenge_equity' => $this->formatMoney((float) $calculation['challenge_equity']),
                'realized_profit' => $this->formatMoney((float) $calculation['realized_profit']),
                'today_profit' => $this->formatMoney((float) $calculation['today_profit']),
                'daily_loss_used' => $this->formatMoney((float) $calculation['daily_loss_used']),
                'daily_loss_remaining' => $this->formatMoney((float) $calculation['daily_loss_remaining']),
                'daily_loss_limit' => $this->formatMoney((float) $calculation['daily_loss_limit']),
                'max_drawdown_used' => $this->formatMoney((float) $calculation['max_drawdown_used']),
                'max_drawdown_remaining' => $this->formatMoney((float) $calculation['max_drawdown_remaining']),
                'max_drawdown_limit' => $this->formatMoney((float) $calculation['max_drawdown_limit']),
                'profit_target_progress' => number_format((float) $calculation['profit_target_progress_percent'], 1).'%',
                'breach_status' => (bool) $calculation['breach']
                    ? $this->humanizeStatus((string) $calculation['breach_reason'])
                    : 'No breach',
            ];
        }

        return [
            'challenge_balance' => $this->formatMoney((float) ($account?->balance ?? 0)),
            'challenge_equity' => $this->formatMoney((float) ($account?->equity ?? 0)),
            'realized_profit' => $this->formatMoney((float) ($account?->total_profit ?? 0)),
            'today_profit' => $this->formatMoney((float) ($account?->today_profit ?? 0)),
            'daily_loss_used' => $this->formatMoney((float) ($account?->daily_loss_used ?? 0)),
            'daily_loss_remaining' => $this->formatMoney(max((float) ($account?->daily_drawdown_limit_amount ?? 0) - (float) ($account?->daily_loss_used ?? 0), 0)),
            'daily_loss_limit' => $this->formatMoney((float) ($account?->daily_drawdown_limit_amount ?? 0)),
            'max_drawdown_used' => $this->formatMoney((float) ($account?->max_drawdown_used ?? 0)),
            'max_drawdown_remaining' => $this->formatMoney(max((float) ($account?->max_drawdown_limit_amount ?? 0) - (float) ($account?->max_drawdown_used ?? 0), 0)),
            'max_drawdown_limit' => $this->formatMoney((float) ($account?->max_drawdown_limit_amount ?? 0)),
            'profit_target_progress' => number_format((float) ($account?->profit_target_progress_percent ?? 0), 1).'%',
            'breach_status' => $account?->failure_reason ? $this->humanizeStatus((string) $account->failure_reason) : 'No breach',
        ];
    }

    /**
     * @return array{status:string,symbol:string,date_from:string,date_to:string,date_filter:string}
     */
    private function adminTradeFilters(Request $request): array
    {
        $status = strtolower((string) $request->query('trade_status', 'both'));
        $dateFilter = strtolower((string) $request->query('date_filter', ''));
        $dateFilter = $dateFilter === 'today' ? 'today' : '';
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        if ($dateFilter === 'today') {
            $dateFrom = now()->toDateString();
            $dateTo = now()->toDateString();
        }

        return [
            'status' => in_array($status, ['both', 'open', 'closed'], true) ? $status : 'both',
            'symbol' => trim((string) $request->query('symbol', '')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_filter' => $dateFilter,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array{status:string,symbol:string,date_from:string,date_to:string,date_filter:string}  $filters
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

        return new LengthAwarePaginator(
            $items,
            count($rows),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
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

    /**
     * @return array<string, mixed>
     */
    private function metaApiSummary(): array
    {
        $accounts = collect(self::PHASE1_VALIDATED_METAAPI_LOGINS)
            ->map(fn (string $login): ?TradingAccount => $this->accountForLogin($login))
            ->filter(fn (?TradingAccount $account): bool => $account instanceof TradingAccount)
            ->unique('id')
            ->values();
        $validatedAccountRows = collect(self::PHASE1_VALIDATED_METAAPI_LOGINS)
            ->map(fn (string $login): array => $this->validatedMetaApiAccountRow($login))
            ->all();

        return [
            'total' => $accounts->count(),
            'connected' => $accounts->filter(fn (TradingAccount $account): bool => $this->mt5ConnectorStatus->status($account) === Mt5ConnectorStatus::CONNECTED)->count(),
            'disconnected' => $accounts->filter(fn (TradingAccount $account): bool => $this->mt5ConnectorStatus->status($account) === Mt5ConnectorStatus::DISCONNECTED)->count(),
            'stale' => $accounts->filter(fn (TradingAccount $account): bool => $this->mt5ConnectorStatus->status($account) === Mt5ConnectorStatus::STALE)->count(),
            'breached' => $accounts->filter(fn (TradingAccount $account): bool => $account->challenge_status === 'failed' || filled((string) $account->failure_reason))->count(),
            'sync_issues' => $accounts->filter(fn (TradingAccount $account): bool => $account->sync_status === 'error' || filled((string) $account->sync_error))->count(),
            'onboarding_queue' => $accounts->filter(fn (TradingAccount $account): bool => in_array((string) data_get($account->meta, 'metaapi_onboarding.state'), ['purchased', 'account_assigned', 'waiting_metaapi_connection', 'first_sync_received'], true))->count(),
            'ready_to_trade' => $accounts->filter(fn (TradingAccount $account): bool => $this->metaApiReadyToTrade($account))->count(),
            'onboarding_failures' => $accounts->filter(fn (TradingAccount $account): bool => data_get($account->meta, 'metaapi_onboarding.retry.last_error') !== null)->count(),
            'pool_available' => Mt5AccountPoolEntry::query()
                ->where('is_available', true)
                ->whereNull('allocated_trading_account_id')
                ->count(),
            'pool_unassigned_metaapi' => Mt5AccountPoolEntry::query()
                ->whereNotNull('meta->metaapi_account_id')
                ->whereNull('allocated_trading_account_id')
                ->count(),
            'validated_accounts' => self::PHASE1_VALIDATED_METAAPI_LOGINS,
            'validated_account_rows' => $validatedAccountRows,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function validatedMetaApiAccountRow(string $login): array
    {
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            return [
                'login' => $login,
                'reference' => 'Missing trading account',
                'source' => 'MetaApi',
                'connection' => 'Missing',
                'lifecycle' => 'Missing',
                'onboarding' => 'Missing',
                'ready_to_trade' => 'No',
                'last_sync' => 'N/A',
                'metrics_url' => null,
            ];
        }

        $connector = $this->mt5ConnectorStatus->forAccount($account);

        return [
            'login' => $login,
            'reference' => (string) ($account->account_reference ?? 'N/A'),
            'source' => $account->sync_source ? $this->sourceLabel((string) $account->sync_source) : 'N/A',
            'connection' => $connector['label'],
            'lifecycle' => $this->humanizeStatus((string) data_get($account->meta, 'metaapi_lifecycle.state', 'waiting_for_first_sync')),
            'onboarding' => $this->humanizeStatus((string) data_get($account->meta, 'metaapi_onboarding.state', 'pending')),
            'ready_to_trade' => $this->metaApiReadyToTrade($account) ? 'Yes' : 'No',
            'last_sync' => $this->formatDateTime($connector['last_sync_at'] ?? $account->last_synced_at),
            'metrics_url' => $account->user_id !== null
                ? route('admin.clients.metrics', [
                    'user' => $account->user_id,
                    'account' => $account->id,
                ])
                : null,
        ];
    }

    private function accountForLogin(string $login): ?TradingAccount
    {
        $account = TradingAccount::query()
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login)
                    ->orWhere('account_reference', $login)
                    ->orWhere('account_reference', 'like', '%'.$login.'%')
                    ->orWhere('meta->mt5_sync->identifier', $login)
                    ->orWhere('meta->mt5_pool_entry->login', $login);
            })
            ->latest('id')
            ->first();

        if ($account instanceof TradingAccount) {
            return $account;
        }

        return Mt5AccountPoolEntry::query()
            ->where('login', $login)
            ->latest('allocated_at')
            ->latest('id')
            ->first()
            ?->allocatedTradingAccount;
    }

    private function metaApiReadyToTrade(TradingAccount $account): bool
    {
        $state = (string) data_get($account->meta, 'metaapi_onboarding.state');

        if (! in_array($state, ['ready_to_trade', 'active'], true)) {
            return false;
        }

        if ($account->challenge_status === 'failed'
            || filled((string) $account->failure_reason)
            || (bool) $account->final_state_locked
            || (bool) $account->trading_blocked
        ) {
            return false;
        }

        if (in_array((string) $account->platform_status, ['stale', 'disconnected', 'disabled', 'disable_requested', 'disable_pending_ack', 'disable_failed'], true)) {
            return false;
        }

        if ((string) $account->sync_status === 'error') {
            return false;
        }

        $syncHealth = (string) data_get($account->meta, 'metaapi_lifecycle.sync_health');
        $coreHealth = (string) data_get($account->meta, 'metaapi_lifecycle.core_sync_health', $syncHealth);

        if (in_array($syncHealth, ['stale', 'disconnected'], true) || in_array($coreHealth, ['stale', 'disconnected'], true)) {
            return false;
        }

        $lastSync = $account->last_synced_at
            ?? data_get($account->meta, 'mt5_sync.last_successful_metric_update_at')
            ?? data_get($account->meta, 'mt5_sync.last_synced_at');

        return ((string) data_get($account->meta, 'metaapi_lifecycle.state') === 'connected' || (string) $account->platform_status === 'connected')
            && $lastSync !== null
            && is_numeric($account->balance)
            && is_numeric($account->equity);
    }

    private function metaApiPhaseOneReady(TradingAccount $account): bool
    {
        if ((string) $account->sync_source !== 'metaapi'
            || $account->challenge_status === 'failed'
            || filled((string) $account->failure_reason)
            || (bool) $account->final_state_locked
        ) {
            return false;
        }

        $state = (string) data_get($account->meta, 'metaapi_lifecycle.state');
        $syncHealth = (string) data_get($account->meta, 'metaapi_lifecycle.sync_health');
        $coreHealth = (string) data_get($account->meta, 'metaapi_lifecycle.core_sync_health', $syncHealth);

        return $state === 'connected'
            && in_array($syncHealth, ['connected', 'recovered', 'degraded'], true)
            && in_array($coreHealth, ['connected', 'recovered', 'degraded'], true)
            && $account->last_synced_at !== null
            && is_numeric($account->balance)
            && is_numeric($account->equity);
    }

    private function sourceLabel(string $source): string
    {
        return match (strtolower($source)) {
            'metaapi' => 'MetaApi',
            'mt5_ea' => 'MT5 EA',
            'admin_activation' => 'Admin activation',
            default => $this->humanizeStatus($source),
        };
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

    private function isMt5Account(TradingAccount $account): bool
    {
        return strtolower((string) $account->platform_slug) === 'mt5'
            || strtolower((string) $account->platform) === 'mt5';
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
        $prefix = $amount < 0 ? '-' : '';
        $absoluteAmount = abs($amount);

        return match (strtoupper($currency)) {
            'EUR' => $prefix.'€'.number_format($absoluteAmount, 2),
            'GBP' => $prefix.'£'.number_format($absoluteAmount, 2),
            default => $prefix.'$'.number_format($absoluteAmount, 2),
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
                return Carbon::parse($value)->format('Y-m-d H:i');
            } catch (\Throwable) {
                return $value;
            }
        }

        return 'N/A';
    }

    private function formatDiagnosticPayload(mixed $payload): string
    {
        if ($payload === null || $payload === '') {
            return 'N/A';
        }

        if (is_scalar($payload)) {
            return (string) $payload;
        }

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable) {
            return 'Unable to render payload';
        }
    }

    private function humanizeStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }
}
