<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Services\TradingAccounts\TradingAccountSnapshotApplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TradingAccountMetricsController extends Controller
{
    public function __invoke(
        Request $request,
        string $accountIdentifier,
        TradingAccountSnapshotApplyService $snapshotApplyService,
    ): JsonResponse {
        $this->authorizeIngestion($request);

        $validated = Validator::make($request->all(), [
            'balance' => ['required', 'numeric'],
            'equity' => ['required', 'numeric'],
            'open_profit' => ['nullable', 'numeric'],
            'timestamp' => ['required', 'date'],
            'server_day' => ['nullable', 'date'],
            'platform_status' => ['nullable', 'string', 'max:255'],
            'platform_environment' => ['nullable', 'string', 'max:255'],
            'trade_count' => ['nullable', 'integer', 'min:0'],
            'activity_count' => ['nullable', 'integer', 'min:0'],
            'has_activity' => ['nullable', 'boolean'],
            'volume' => ['nullable', 'numeric', 'min:0'],
            'phase_index' => ['nullable', 'integer', 'min:1'],
            'raw' => ['nullable', 'array'],
        ])->validate();

        $account = $this->resolveAccount($accountIdentifier);
        $startedAt = now();

        $log = TradingAccountSyncLog::query()->create([
            'trading_account_id' => $account->id,
            'platform' => $account->platform_slug ?: 'mt5',
            'status' => 'started',
            'message' => 'MT5 metrics update received.',
            'started_at' => $startedAt,
            'payload' => $request->all(),
        ]);

        try {
            $updatedAccount = $snapshotApplyService->apply($account, array_merge($validated, [
                'raw' => $request->all(),
            ]), [
                'source' => 'mt5_ea',
                'started_at' => $startedAt,
                'snapshot_at' => $validated['timestamp'],
            ]);

            $log->forceFill([
                'status' => 'success',
                'message' => 'MT5 metrics applied successfully.',
                'completed_at' => now(),
            ])->save();

            return response()->json([
                'status' => 'ok',
                'account_id' => $updatedAccount->id,
                'account_reference' => $updatedAccount->account_reference,
                'challenge_status' => $updatedAccount->challenge_status,
                'phase_index' => (int) $updatedAccount->phase_index,
                'trading_days_completed' => (int) $updatedAccount->trading_days_completed,
                'failure_reason' => $updatedAccount->failure_reason,
                'last_synced_at' => optional($updatedAccount->last_synced_at)->toIso8601String(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            $log->forceFill([
                'status' => 'error',
                'message' => 'MT5 metrics update failed.',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            TradingAccount::query()->whereKey($account->id)->update([
                'sync_status' => 'error',
                'sync_source' => 'mt5_ea',
                'sync_error' => $exception->getMessage(),
                'sync_error_at' => now(),
                'last_sync_started_at' => $startedAt,
                'last_sync_completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function authorizeIngestion(Request $request): void
    {
        $configuredToken = (string) config('services.mt5_ingestion.token');
        $providedToken = (string) ($request->bearerToken() ?: $request->header('X-Wolforix-Token', ''));

        abort_unless(
            $configuredToken !== '' && hash_equals($configuredToken, $providedToken),
            401,
            'Invalid integration token.',
        );
    }

    private function resolveAccount(string $accountIdentifier): TradingAccount
    {
        $account = TradingAccount::query()
            ->where('platform_account_id', $accountIdentifier)
            ->orWhere('platform_login', $accountIdentifier)
            ->orWhere('account_reference', $accountIdentifier)
            ->when(is_numeric($accountIdentifier), function ($query) use ($accountIdentifier) {
                $query->orWhere('id', (int) $accountIdentifier);
            })
            ->first();

        if (! $account instanceof TradingAccount) {
            throw ValidationException::withMessages([
                'account' => 'Trading account not found for the provided identifier.',
            ]);
        }

        return $account;
    }
}
