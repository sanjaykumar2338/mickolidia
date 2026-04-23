<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Admin\AdminChallengeActivationService;
use App\Services\Challenge\ChallengeLifecycleMailer;
use App\Services\TradingAccounts\TradeHistoryPanelBuilder;
use Illuminate\Support\Arr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminClientController extends Controller
{
    public function __construct(
        private readonly TradeHistoryPanelBuilder $tradeHistoryPanelBuilder,
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
                'last_synced_at' => $this->formatDateTime($selectedAccount?->last_synced_at),
                'last_evaluated_at' => $this->formatDateTime($selectedAccount?->last_evaluated_at),
                'sync_source' => $selectedAccount?->sync_source ? $this->humanizeStatus((string) $selectedAccount->sync_source) : 'N/A',
                'sync_error' => $selectedAccount?->sync_error ?? 'None',
                'authorized_accounts_count' => is_array($user->ctraderConnection?->authorized_accounts) ? count($user->ctraderConnection->authorized_accounts) : 0,
                'last_authorized_at' => $this->formatDateTime($user->ctraderConnection?->last_authorized_at),
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
        return config("wolforix.checkout_countries.{$countryCode}", $countryCode);
    }

    private function formatMoney(float $amount, string $currency = 'USD'): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€'.number_format($amount, 2),
            'GBP' => '£'.number_format($amount, 2),
            default => '$'.number_format($amount, 2),
        };
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return 'Not synced yet';
    }

    private function humanizeStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }
}
