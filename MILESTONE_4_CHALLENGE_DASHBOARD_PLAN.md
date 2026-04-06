# Milestone 4: Challenge Logic & Dashboard Plan

## 1. Current Structure

### Existing core models
- `app/Models/TradingAccount.php`
  - already stores the main account, challenge, balance, equity, drawdown, target, phase, and sync fields
  - already has relations for status histories, balance snapshots, and sync logs
- `app/Models/ChallengePurchase.php`
  - links the purchased challenge to the trading account lifecycle
- `app/Models/Order.php`
  - payment/order source for account provisioning
- `app/Models/TradingAccountBalanceSnapshot.php`
  - stores per-sync balance/equity/profit/drawdown snapshots
- `app/Models/TradingAccountStatusHistory.php`
  - stores account-status and phase changes
- `app/Models/TradingAccountSyncLog.php`
  - stores sync lifecycle and payload diagnostics

### Existing services
- `app/Services/TradingAccounts/TradingAccountProvisioner.php`
  - creates the initial challenge trading account from a paid order
- `app/Services/TradingAccounts/TradingAccountSyncService.php`
  - fetches a platform snapshot, calculates metrics, runs rule evaluation, writes snapshots/status history
- `app/Services/ChallengeRuleEngine.php`
  - current rule engine for basic target/drawdown evaluation
- `app/Services/TradingAccounts/ChallengeRuleEvaluator.php`
  - thin wrapper around the current rule engine

### Existing controllers and views
- `app/Http/Controllers/DashboardController.php`
  - already prepares challenge account payloads for user dashboard cards/overview
- `app/Http/Controllers/AdminClientController.php`
  - already shows latest trading-account state for admin
- `resources/views/dashboard/index.blade.php`
- `resources/views/dashboard/accounts.blade.php`
- `resources/views/admin/clients/show.blade.php`

### Existing config
- `config/wolforix.php`
  - already contains challenge catalog/rules for `one_step` and `two_step`
  - rules are centralized enough to reuse instead of hardcoding values again

### Existing limitation
- current sync/evaluation is cTrader-oriented and snapshot-based
- there is no dedicated ingestion endpoint yet for MT5 EA updates
- there is no day-level activity table for correct trading-day counting

## 2. Missing Data Points

The current schema is close, but it does not fully support the milestone rules and auditability.

### Missing or incomplete on `trading_accounts`
- `highest_equity_today`
- `daily_loss_used`
- `max_drawdown_used`
- `phase_starting_balance`
- `phase_starting_equity_reference`
- `phase_started_at`
- `challenge_status` or a normalized challenge-state value separate from generic UI `status`
- `failure_reason`
- `failure_context`
- `sync_source`
- `server_day`
- `last_evaluated_at`

### Missing related persistence
- `trading_account_days` table to count valid trading days without double counting
  - should store one row per `trading_account_id + trading_date`
  - should track whether actual activity occurred and what source created the day
- optionally an account metric event/log layer later, but not required for this milestone

### Existing fields that can be reused
- `balance`, `equity`, `starting_balance`
- `daily_drawdown`, `max_drawdown`
- `profit_target_percent`, `profit_target_amount`, `profit_target_progress_percent`
- `daily_drawdown_limit_percent`, `daily_drawdown_limit_amount`
- `max_drawdown_limit_percent`, `max_drawdown_limit_amount`
- `minimum_trading_days`, `trading_days_completed`
- `passed_at`, `failed_at`, `last_synced_at`, `rule_state`

## 3. Proposed DB / Model / Service Changes

### Database changes
- add a safe migration to extend `trading_accounts` with:
  - `highest_equity_today`
  - `daily_loss_used`
  - `max_drawdown_used`
  - `phase_starting_balance`
  - `phase_peak_equity`
  - `phase_started_at`
  - `failure_reason`
  - `failure_context`
  - `challenge_status`
  - `sync_source`
  - `server_day`
  - `last_evaluated_at`
- add `trading_account_days` table with:
  - `trading_account_id`
  - `trading_date`
  - `activity_count`
  - `volume`
  - `first_activity_at`
  - `last_activity_at`
  - `source`
  - unique key on `(trading_account_id, trading_date)`

### Model changes
- extend `TradingAccount` casts/fillable access for new fields
- add `TradingAccountDay` model and relation from `TradingAccount`

### Service changes
- create `app/Services/Challenge/ChallengeProgressEngine.php`
  - centralized challenge evaluation service for both EA ingestion and existing sync paths
- keep `config/wolforix.php` as the single source of 1-step / 2-step rules
- adapt or retire `App\Services\ChallengeRuleEngine` so rule logic lives in one place only
- update `TradingAccountSyncService` to use the new progress engine

### API / ingestion changes
- add `routes/api.php`
- add authenticated endpoint such as:
  - `POST /api/integrations/mt5/accounts/{account}/metrics`
- create controller under:
  - `app/Http/Controllers/Api/TradingAccountMetricsController.php`
- use token/header-based shared-secret auth from config/env

## 4. Challenge Rule Flow

### Shared evaluation flow
1. receive account metrics update
2. resolve trading account by platform account id / login / reference
3. normalize snapshot values
4. determine current server day from payload timestamp
5. update daily reference values
6. upsert trading-day activity row if the payload shows real trading activity
7. run challenge evaluation for the current phase
8. persist account metrics, rule usage, status, and audit trail

### 1-Step challenge flow
- phase index stays `1`
- rules:
  - profit target `10%`
  - daily loss limit `4%`
  - max drawdown `8%`
  - minimum trading days `3`
- pass only when:
  - target reached
  - minimum trading days reached
  - account not already failed
- fail immediately on:
  - `daily_loss_breached`
  - `max_drawdown_breached`

### 2-Step challenge flow

#### Phase 1
- target `10%`
- daily loss limit `5%`
- max drawdown `10%`
- minimum trading days `3`

#### Phase 1 pass transition
- mark phase 1 complete in rule state/history
- create auditable phase transition entry in status history context
- reset phase reference values:
  - `phase_index = 2`
  - `phase_started_at = now/server timestamp`
  - `phase_starting_balance = current balance`
  - `phase_peak_equity = current equity`
  - `highest_equity_today = current equity` for the new day context
  - reset used drawdown/progress figures for phase 2 calculations
- keep prior phase details in `rule_state` / history instead of overwriting silently

#### Phase 2
- target `5%`
- daily loss limit `5%`
- max drawdown `10%`
- minimum trading days `3`
- final pass marks challenge completed/passed

### Daily loss logic
- daily loss uses highest equity reached during the current server day
- resets when the payload day crosses `00:00` server time
- formula basis:
  - `daily_loss_used = max(0, highest_equity_today - current_equity)`
- limit amount is phase starting balance times daily loss limit percent

### Max drawdown logic
- use phase reference balance / peak reference for current phase
- in this milestone, use loss from phase starting balance as the prop-firm baseline
- update `max_drawdown_used` and fail when it exceeds the configured limit

## 5. Dashboard Sections To Update

### User dashboard
- `resources/views/dashboard/index.blade.php`
  - show status, challenge type, current phase, last update, failure reason if failed
- `resources/views/dashboard/accounts.blade.php`
  - add:
    - account size
    - challenge type
    - current phase
    - challenge status
    - current balance
    - current equity
    - profit target progress
    - daily loss used / remaining
    - max drawdown used / remaining
    - trading days completed vs minimum
    - last synced / last evaluated
    - failure reason if present

### Admin
- `resources/views/admin/clients/show.blade.php`
  - add:
    - challenge phase
    - challenge status
    - failure reason
    - sync source
    - last update
    - rule usage summary

### Rendering approach
- no full redesign
- keep current Wolforix panel/card style
- surface rule clarity and account state, not decorative UI changes

## 6. Assumptions

### Data/source assumptions
- MT5 EA or integration layer can send:
  - account identifier
  - balance
  - equity
  - open profit
  - timestamp
  - server day reference
  - activity/trade count or a signal that a trade happened
- server day in the payload is trusted or can be derived from server timestamp

### Product assumptions
- this milestone does not create MT5 accounts automatically
- existing cTrader-linked accounts must continue working
- the new engine should support both:
  - existing internal sync service
  - future MT5 ingestion
- phase 2 progression should happen on the same logical trading account record for now, with history preserved in rule state and status history

### Pending decisions / future work
- if the EA later sends richer trade-level payloads, trading-day counting can be made more exact without changing the dashboard contract
- if the business later wants separate records per phase, the engine should still be reusable, but this milestone will keep one account record plus phase history
- automatic funded-account provisioning after passing is explicitly out of scope here
