<?php

namespace App\Http\Controllers;

use App\Jobs\SyncCTraderAccount;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\CTraderService;
use App\Services\TradingAccounts\TradingAccountSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CTraderAuthController extends Controller
{
    public function __construct(
        private readonly CTraderService $ctraderService,
    ) {
    }

    public function redirect(): RedirectResponse
    {
        if (! $this->ctraderService->isConfigured()) {
            return redirect()
                ->route('dashboard.accounts')
                ->with('error', 'cTrader client credentials are not configured yet.');
        }

        return redirect()->away($this->ctraderService->buildAuthorizationUrl());
    }

    public function callback(Request $request, TradingAccountSyncService $syncService): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()
                ->route('dashboard.accounts')
                ->with('error', 'cTrader did not return an authorization code.');
        }

        try {
            $connection = $this->ctraderService->authorizeUser($user, $code);
            $accounts = $user->challengeTradingAccounts()
                ->where('platform_slug', 'ctrader')
                ->where('ctrader_connection_id', $connection->id)
                ->get();

            foreach ($accounts as $account) {
                if ((bool) config('trading.sync.use_queue', true)) {
                    SyncCTraderAccount::dispatch($account->id);

                    continue;
                }

                $syncService->sync($account);
            }

            return redirect()
                ->route('dashboard.accounts')
                ->with('status', 'cTrader authorization completed successfully.');
        } catch (Throwable $exception) {
            return redirect()
                ->route('dashboard.accounts')
                ->with('error', $exception->getMessage());
        }
    }

    public function linkAccount(Request $request, TradingAccountSyncService $syncService): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'trading_account_id' => ['required', 'integer'],
            'platform_account_id' => ['required', 'string'],
        ]);

        $account = $user->challengeTradingAccounts()
            ->whereKey((int) $validated['trading_account_id'])
            ->where('platform_slug', 'ctrader')
            ->first();

        if (! $account instanceof TradingAccount) {
            return redirect()
                ->route('dashboard.accounts')
                ->with('error', 'The selected Wolforix trading account could not be found.');
        }

        try {
            $linkedAccount = $this->ctraderService->linkAuthorizedAccountToTradingAccount($account, (string) $validated['platform_account_id']);

            if ((bool) config('trading.sync.use_queue', true)) {
                SyncCTraderAccount::dispatch($linkedAccount->id);
            } else {
                $syncService->sync($linkedAccount);
            }

            return redirect()
                ->route('dashboard.accounts')
                ->with('status', 'The cTrader account was linked successfully.');
        } catch (Throwable $exception) {
            return redirect()
                ->route('dashboard.accounts')
                ->with('error', $exception->getMessage());
        }
    }
}
