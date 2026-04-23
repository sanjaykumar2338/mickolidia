<?php

namespace App\Http\Controllers;

use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Wolfi\WolfiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardWolfiController extends Controller
{
    public function __invoke(Request $request, WolfiAssistantService $wolfiAssistantService): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'page' => ['nullable', 'string', 'in:dashboard,dashboard.accounts,dashboard.payouts,dashboard.settings,dashboard.wolfi.voices'],
            'account_id' => ['nullable', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $page = (string) ($validated['page'] ?? 'dashboard');
        $account = $this->resolveAccount($user, $validated['account_id'] ?? null);

        return response()->json(
            $wolfiAssistantService->respond(
                $user,
                $account,
                $page,
                (string) $validated['message'],
            )
        );
    }

    private function resolveAccount(User $user, mixed $requestedAccountId): ?TradingAccount
    {
        $accounts = $user->challengeTradingAccounts()
            ->latest('created_at')
            ->get();

        if (! is_numeric($requestedAccountId)) {
            return $accounts->first();
        }

        return $accounts->firstWhere('id', (int) $requestedAccountId) ?? $accounts->first();
    }
}
