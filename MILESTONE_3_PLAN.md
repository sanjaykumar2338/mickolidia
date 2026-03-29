# Wolforix Milestone 3 Plan

## 1. Current Architecture Summary

- Public challenge checkout is handled by `CheckoutController`, `Order`, `ChallengePlan`, and `OrderFulfillmentService`.
- Stripe fulfillment is handled through `StripeWebhookController` and `OrderFulfillmentService::markPaid()`.
- Paid purchases create a `challenge_purchases` record, but there is no paid-account provisioning layer yet.
- `TradingAccount` already exists and is currently used mainly by the free trial flow.
- The dashboard exists, but the main account metrics shown on the authenticated dashboard are still hard-coded demo arrays in `DashboardController`.
- Admin client pages exist and already read `latestTradingAccount`, `latestOrder`, and `latestChallengePurchase`.
- Jobs and queues are available (`jobs` table migration exists, `QUEUE_CONNECTION=database` in `.env.example`), but only mail-oriented jobs are currently present.
- There is no existing broker/trading-platform abstraction, no cTrader client, and no scheduler configuration yet.

## 2. Existing Account / Challenge Lifecycle

1. User authenticates and completes checkout.
2. `orders` row is created in pending state.
3. Stripe success/webhook calls `OrderFulfillmentService::markPaid()`.
4. The order is marked paid/completed.
5. A `challenge_purchases` row is created with `account_status = pending_activation`.
6. User/admin can see purchase/order data, but no challenge trading account is provisioned automatically.
7. Trial accounts are the only flow that currently creates `trading_accounts` records directly.

## 3. Where cTrader Integration Fits

- Milestone 3 should extend the paid challenge lifecycle after successful payment.
- `challenge_purchases` remains the commercial record; `trading_accounts` becomes the operational trading-account record for both challenge and funded phases.
- cTrader integration should:
  - attach external platform identifiers to `trading_accounts`
  - sync external account state into local metrics/history tables
  - evaluate rule status locally
  - feed dashboard/admin visibility from database-backed account records

## 4. Proposed Database Additions

### Expand `trading_accounts`

- Link commercial records:
  - `order_id`
  - `challenge_purchase_id`
- External platform fields:
  - `platform_slug`
  - `platform_account_id`
  - `platform_login`
  - `platform_environment`
  - `platform_status`
- Lifecycle fields:
  - `account_phase`
  - `phase_index`
  - `account_status`
  - `is_funded`
  - `passed_at`
  - `failed_at`
  - `activated_at`
- Sync fields:
  - `sync_status`
  - `last_synced_at`
  - `last_sync_started_at`
  - `last_sync_completed_at`
  - `sync_error`
  - `sync_error_at`
- Metrics / rules:
  - `profit_target_percent`
  - `profit_target_amount`
  - `profit_target_progress_percent`
  - `daily_drawdown_limit_percent`
  - `daily_drawdown_limit_amount`
  - `max_drawdown_limit_percent`
  - `max_drawdown_limit_amount`
  - `profit_split`
  - `payout_eligible_at`
  - `first_payout_eligible_at`
  - `payout_cycle_started_at`
  - `trading_days_required`
  - `trading_days_count`
  - `last_balance_change_at`

### New history/support tables

- `trading_account_status_histories`
  - account status transitions and source metadata
- `trading_account_balance_snapshots`
  - balance/equity/PnL snapshots per sync
- `trading_account_sync_logs`
  - sync attempts, status, and failure details

## 5. Proposed Service / Job / Sync Architecture

### Services

- `App\Services\TradingPlatforms\TradingPlatformClientInterface`
  - common shape for future platform adapters
- `App\Services\TradingPlatforms\CTraderService`
  - env/config-driven cTrader API client
  - account fetch methods
  - response normalization
  - safe credential checks
- `App\Services\TradingAccounts\TradingAccountProvisioner`
  - create a local `TradingAccount` for paid purchases
- `App\Services\TradingAccounts\TradingAccountSyncService`
  - orchestrate one account sync
  - store snapshots/logs
  - update current account fields
- `App\Services\TradingAccounts\ChallengeRuleEvaluator`
  - centralized pass/fail/rule evaluation
- `App\Support\TradingMetricsCalculator`
  - compute PnL, drawdown, progress, payout windows, and derived values

### Jobs / Commands / Scheduling

- `SyncTradingAccountJob`
  - sync one active account
- `SyncActiveTradingAccountsJob`
  - optional fan-out launcher
- `php artisan trading:sync-accounts`
  - manual or scheduled sync entrypoint
- Scheduler
  - periodic sync when feature flag is enabled

## 6. Phased Implementation Plan

### Phase 1
- Add additive migrations and model relationships.
- Make paid challenge provisioning create a `trading_accounts` row.

### Phase 2
- Add cTrader config and service abstraction.
- Make missing credentials fail safely without breaking checkout/dashboard.

### Phase 3
- Add sync job/command/logging path.
- Persist snapshots and sync state locally.

### Phase 4
- Add rule evaluation and metric calculation services.
- Centralize challenge rule logic from catalog/config into reusable evaluators.

### Phase 5
- Replace dashboard demo arrays with real account-backed view data and clean empty states.

### Phase 6
- Extend admin visibility using existing client pages instead of introducing a new admin system.

## 7. Assumptions / Missing Credentials / API Unknowns

- cTrader client credentials, account-access scopes, and exact base URLs are not present in the repo.
- Exact cTrader API endpoints and payload shapes for account summary, equity/balance, open positions, and trade history are not yet confirmed locally.
- Account creation inside cTrader may require a broker-side flow outside this application; Milestone 3 will prepare Wolforix to store and sync linked accounts, not fake external provisioning.
- Until live credentials exist, sync should support a disabled mode and optional mock/sample response data for local development.

## 8. Testing Plan

### Backend

- migration tests for new tables/columns
- provisioning test: paid purchase creates linked trading account
- sync service tests for:
  - missing credentials
  - successful normalized sync
  - failure logging
- rule evaluator tests for progress/pass/fail logic

### Dashboard / Admin

- authenticated user only sees their own trading accounts
- empty states render cleanly when no synced accounts exist
- admin pages expose platform IDs, sync status, and metrics

### Regression

- login, checkout, Stripe webhook fulfillment, multilingual public pages, and existing trial flow continue to pass
