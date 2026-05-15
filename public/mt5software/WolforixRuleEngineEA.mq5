#property strict
#property version   "1.00"
#property description "Wolforix MT5 rule engine"

#include "Include\\WolforixTypes.mqh"
#include "Include\\WolforixEngine.mqh"
#include "Include\\WolforixDisplay.mqh"
#include "Include\\WolforixSync.mqh"

input WFChallengePreset InpChallengePreset      = WF_CHALLENGE_ONE_STEP;
input string            InpChallengeId          = "wolforix-m1";
input double            InpInitialBalanceOverride = 0.0;
input WFDailyLossMode   InpDailyLossMode        = WF_DAILY_LOSS_FROM_DAY_START_BALANCE;
input int               InpTimerIntervalSeconds = 1;
input int               InpPersistIntervalSeconds = 5;
input bool              InpWriteBridgeSnapshot  = true;
input bool              InpResetStoredState     = false;
input string            ApiBaseUrl              = "https://www.wolforix.com";
input string            ApiToken                = "";
input string            AccountReference        = "";
input bool              EnableSync              = true;
input int               SyncIntervalSeconds     = 30;
input int               TradeEventSyncCooldownSeconds = 2;
input int               FloatingSyncMinIntervalSeconds = 3;
input int               OpenPositionHeartbeatSeconds = 10;
input double            FloatingSyncThresholdAmount = 10.0;
input int               CloseRetryAttempts       = 3;
input int               CloseRetryDelayMilliseconds = 1500;
input bool              DiagnosticOnly           = true;
input bool              UseBackendRuleDecision   = true;
input bool              EnforceLocalRulesWhenBackendUnavailable = false;

WFRuleSet      g_rules;
WFRuntimeState g_state;
WFMetrics      g_metrics;
WFSyncState    g_sync_state;
WFCloseReport  g_close_report;
CTrade         g_trade;
string         g_state_file_path = "";
string         g_snapshot_file_path = "";
string         g_last_action = "Initialized";
datetime       g_last_state_persist_at = 0;
datetime       g_last_snapshot_at = 0;
datetime       g_last_sync_at = 0;
datetime       g_last_successful_sync_at = 0;
bool           g_state_dirty = false;
bool           g_refresh_trading_days = true;
double         g_last_synced_balance = 0.0;
double         g_last_synced_equity = 0.0;
double         g_last_synced_floating_pnl = 0.0;
int            g_last_synced_positions = 0;
string         g_last_successful_sync_signature = "";

bool PersistStateIfNeeded(const bool force_write)
  {
   datetime now = WFServerNow();
   bool state_interval_reached = ((now - g_last_state_persist_at) >= InpPersistIntervalSeconds);
   bool snapshot_interval_reached = ((now - g_last_snapshot_at) >= InpPersistIntervalSeconds);
   bool should_write_state = force_write || (g_state_dirty && state_interval_reached);
   bool should_write_snapshot = InpWriteBridgeSnapshot && (force_write || snapshot_interval_reached || g_state_dirty);

   if(!should_write_state && !should_write_snapshot)
      return true;

   g_state.LastEvaluationTime = now;

   bool ok = true;
   if(should_write_state)
     {
      ok = WFSaveState(g_state_file_path,g_state);
      if(ok)
        {
         g_last_state_persist_at = now;
         g_state_dirty = false;
        }
     }

   if(ok && should_write_snapshot)
     {
      ok = WFWriteSnapshot(g_snapshot_file_path,g_state,g_rules,g_metrics,InpDailyLossMode);
      if(ok)
         g_last_snapshot_at = now;
     }

   return ok;
  }

void RefreshTradingDayCount(const datetime now)
  {
   if(!g_refresh_trading_days)
      return;

   int count = WFCountTradingDaysFromHistory(g_state.ChallengeStartTime,now);
   if(count < 0)
      return;

   if(count != g_state.LastTradingDayCount)
     {
      g_state.LastTradingDayCount = count;
      g_state_dirty = true;
     }

   g_refresh_trading_days = false;
  }

void MarkStateDirty()
  {
   g_state_dirty = true;
  }

void RefreshChartStatusPanel(const string source)
  {
   datetime now = WFServerNow();
   Comment(WFBuildChartStatus(g_state.ChallengeId,
                              AccountInfoString(ACCOUNT_CURRENCY),
                              WFFmtDateTime(now),
                              g_rules,
                              g_state,
                              g_metrics,
                              g_sync_state,
                              InpDailyLossMode,
                              source + " | " + g_last_action));
  }

string BuildSyncSignature()
  {
   return StringFormat("%.2f|%.2f|%.2f|%.2f|%.2f|%d|%d|%s|%s",
                       g_metrics.Balance,
                       g_metrics.Equity,
                       g_metrics.FloatingPnL,
                       g_metrics.DailyLossAmount,
                       g_metrics.MaxDrawdownAmount,
                       g_metrics.TradingDaysCount,
                       g_state.Status,
                       g_rules.Code,
                       g_state.BreachReason);
  }

void CaptureSuccessfulSyncState(const string signature,const datetime synced_at)
  {
   g_last_successful_sync_at = synced_at;
   g_last_successful_sync_signature = signature;
   g_last_synced_balance = g_metrics.Balance;
   g_last_synced_equity = g_metrics.Equity;
   g_last_synced_floating_pnl = g_metrics.FloatingPnL;
   g_last_synced_positions = PositionsTotal();
  }

string TradeSyncTrigger(const MqlTradeTransaction &trans)
  {
   switch(trans.type)
     {
      case TRADE_TRANSACTION_DEAL_ADD:
         return "trade_deal";
      case TRADE_TRANSACTION_DEAL_UPDATE:
         return "trade_deal_update";
      case TRADE_TRANSACTION_ORDER_ADD:
         return "trade_order_add";
      case TRADE_TRANSACTION_ORDER_UPDATE:
         return "trade_order_update";
      case TRADE_TRANSACTION_ORDER_DELETE:
         return "trade_order_delete";
      case TRADE_TRANSACTION_HISTORY_ADD:
         return "trade_history_add";
      case TRADE_TRANSACTION_POSITION:
         return "trade_position_update";
      default:
         return "trade";
     }
  }

bool MaybeSyncMetricsWithPolicy(const bool force_sync,
                                const string trigger,
                                const int minimum_interval_seconds,
                                const bool skip_if_unchanged)
  {
   g_sync_state.Enabled = EnableSync;

   if(!EnableSync)
     {
      if(g_sync_state.LastAttemptTime <= 0)
         g_sync_state.LastResult = "Disabled";
      return false;
     }

   int sync_interval = minimum_interval_seconds;
   if(sync_interval < 1)
      sync_interval = SyncIntervalSeconds;
   if(sync_interval < 1)
      sync_interval = 1;

   datetime now = WFServerNow();
   string signature = BuildSyncSignature();

   if(skip_if_unchanged && signature == g_last_successful_sync_signature)
      return false;

   if(!force_sync && (now - g_last_sync_at) < sync_interval)
      return false;

   g_last_sync_at = now;
   bool synced = WFSendMetricsToBackend(ApiBaseUrl,
                                        ApiToken,
                                        AccountReference,
                                        g_rules,
                                        g_state,
                                        g_metrics,
                                        g_close_report,
                                        now,
                                        trigger,
                                        g_sync_state);

   if(synced)
      CaptureSuccessfulSyncState(signature,now);

   return synced;
  }

bool MaybeSyncMetrics(const bool force_sync,const string trigger)
  {
   return MaybeSyncMetricsWithPolicy(force_sync,trigger,SyncIntervalSeconds,false);
  }

bool BackendHasSuccessfulResponse()
  {
   return (g_sync_state.LastHttpCode >= 200 &&
           g_sync_state.LastHttpCode < 300 &&
           StringLen(g_sync_state.LastResponseBody) > 0);
  }

bool BackendBoolFieldTrue(const string field_name)
  {
   string body = g_sync_state.LastResponseBody;
   string pattern = "\"" + field_name + "\":true";
   return StringFind(body,pattern) >= 0;
  }

bool BackendStringFieldEquals(const string field_name,const string expected)
  {
   string body = g_sync_state.LastResponseBody;
   string pattern = "\"" + field_name + "\":\"" + expected + "\"";
   return StringFind(body,pattern) >= 0;
  }

string BackendStringFieldValue(const string field_name,const string fallback)
  {
   string body = g_sync_state.LastResponseBody;
   string pattern = "\"" + field_name + "\":\"";
   int start = StringFind(body,pattern);
   if(start < 0)
      return fallback;

   start += StringLen(pattern);
   int finish = StringFind(body,"\"",start);
   if(finish <= start)
      return fallback;

   return StringSubstr(body,start,finish - start);
  }

bool BackendRequiresClose()
  {
   if(!BackendHasSuccessfulResponse())
      return false;

   return BackendBoolFieldTrue("trading_blocked") ||
          BackendBoolFieldTrue("close_positions_required") ||
          BackendBoolFieldTrue("mt5_deactivation_required") ||
          BackendStringFieldEquals("ea_action","close_all_positions_and_disable_account") ||
          BackendStringFieldEquals("ea_action","close_positions");
  }

bool BackendAllowsContinue()
  {
   if(!BackendHasSuccessfulResponse())
      return false;

   return BackendStringFieldEquals("ea_action","continue") &&
          !BackendBoolFieldTrue("trading_blocked") &&
          !BackendBoolFieldTrue("close_positions_required") &&
          !BackendBoolFieldTrue("mt5_deactivation_required");
  }

bool LocalRulesAreAuthoritative()
  {
   if(!UseBackendRuleDecision || !EnableSync)
      return true;

   if(EnforceLocalRulesWhenBackendUnavailable && !BackendHasSuccessfulResponse())
      return true;

   return false;
  }

void ClearLocalFinalStateFromBackendContinue()
  {
   if(!UseBackendRuleDecision || !EnableSync || !BackendAllowsContinue())
      return;

   if(g_state.Status == WF_STATUS_ACTIVE && !g_state.TradingBlocked && StringLen(g_state.BreachReason) == 0)
      return;

   g_state.Status = WF_STATUS_ACTIVE;
   g_state.TradingBlocked = false;
   g_state.BreachReason = "";
   g_state.FailedAt = 0;
   g_state.PassedAt = 0;
   g_last_action = "Backend continue cleared local close state";
   PrintFormat("Wolforix Backend Authority: cleared local final/blocked state because backend response is continue. response=%s",
               g_sync_state.LastResponseBody);
   MarkStateDirty();
  }

void MaybeSyncFloatingState()
  {
   if(!EnableSync)
      return;

   int positions_count = PositionsTotal();
   if(positions_count <= 0)
      return;

   double floating_delta = MathAbs(g_metrics.FloatingPnL - g_last_synced_floating_pnl);
   bool floating_changed = (floating_delta >= MathMax(FloatingSyncThresholdAmount,0.0));
   bool position_count_changed = (positions_count != g_last_synced_positions);
   bool balance_changed = (MathAbs(g_metrics.Balance - g_last_synced_balance) >= 0.01);
   bool equity_changed = (MathAbs(g_metrics.Equity - g_last_synced_equity) >= 0.01);
   bool heartbeat_due = (g_last_successful_sync_at <= 0 ||
                         (WFServerNow() - g_last_successful_sync_at) >= MathMax(OpenPositionHeartbeatSeconds,1));

   if(position_count_changed)
     {
      MaybeSyncMetricsWithPolicy(false,"position_count_change",TradeEventSyncCooldownSeconds,true);
      return;
     }

   if(floating_changed)
     {
      MaybeSyncMetricsWithPolicy(false,"floating_pnl_change",FloatingSyncMinIntervalSeconds,true);
      return;
     }

   if(heartbeat_due && (balance_changed || equity_changed))
      MaybeSyncMetricsWithPolicy(false,"open_position_heartbeat",FloatingSyncMinIntervalSeconds,false);
  }

void EvaluateEngine(const string source)
  {
   datetime now = WFServerNow();
   double balance = AccountInfoDouble(ACCOUNT_BALANCE);
   double equity  = AccountInfoDouble(ACCOUNT_EQUITY);

   if(g_state.ChallengeStartTime <= 0)
     {
      double initial_balance = balance;
      if(InpInitialBalanceOverride > 0.0)
         initial_balance = InpInitialBalanceOverride;

      WFInitializeState(g_state,(long)AccountInfoInteger(ACCOUNT_LOGIN),WFSanitizeKey(InpChallengeId),g_rules,initial_balance,equity,now);
      g_state.DayStartBalance = balance;
      g_state.DayStartEquity  = equity;
      g_state.DayHighestEquity= equity;
      g_state.PeakBalance     = balance;
      g_state.PeakEquity      = equity;
      g_last_action           = "State initialized";
      MarkStateDirty();
     }

   bool day_reset = false;
   WFEnsureDailySession(g_state,now,balance,equity,day_reset);
   if(day_reset)
     {
      g_last_action          = "Daily session reset at 00:00 server time";
      g_refresh_trading_days = true;
      MarkStateDirty();
     }

   if(WFRefreshPeaks(g_state,balance,equity))
      MarkStateDirty();
   RefreshTradingDayCount(now);

   WFComputeMetrics(g_rules,InpDailyLossMode,g_state,balance,equity,g_state.LastTradingDayCount,g_metrics);

   if(g_state.Status == WF_STATUS_ACTIVE)
     {
      bool local_rules_authoritative = LocalRulesAreAuthoritative();

      if(g_metrics.DailyLossBreached)
        {
         if(local_rules_authoritative)
           {
            WFMarkFailure(g_state,now,"Daily loss limit breached");
            g_last_action = "Daily loss breach detected";
            MarkStateDirty();
           }
         else
           {
            g_last_action = "Local daily loss breach observed; waiting for backend decision";
            PrintFormat("Wolforix Local Rule Observation: daily_loss_breached=true but backend authority is enabled. balance=%.2f equity=%.2f daily_loss=%.2f limit=%.2f backend_response=%s",
                        balance,
                        equity,
                        g_metrics.DailyLossAmount,
                        g_metrics.DailyLossLimitAmount,
                        g_sync_state.LastResponseBody);
           }
        }
      else if(g_metrics.MaxDrawdownBreached)
        {
         if(local_rules_authoritative)
           {
            WFMarkFailure(g_state,now,"Maximum drawdown limit breached");
            g_last_action = "Maximum drawdown breach detected";
            MarkStateDirty();
           }
         else
           {
            g_last_action = "Local max drawdown breach observed; waiting for backend decision";
            PrintFormat("Wolforix Local Rule Observation: max_drawdown_breached=true but backend authority is enabled. balance=%.2f equity=%.2f max_drawdown=%.2f limit=%.2f backend_response=%s",
                        balance,
                        equity,
                        g_metrics.MaxDrawdownAmount,
                        g_metrics.MaxDrawdownLimitAmount,
                        g_sync_state.LastResponseBody);
           }
        }
      else if(g_metrics.Passed)
        {
         if(local_rules_authoritative)
           {
            WFMarkPassed(g_state,now);
            g_last_action = "Challenge passed";
            MarkStateDirty();
           }
         else
           {
            g_last_action = "Local pass observed; waiting for backend decision";
            PrintFormat("Wolforix Local Rule Observation: profit_target_reached=true but backend authority is enabled. profit=%.2f target=%.2f backend_response=%s",
                        g_metrics.ProfitAmount,
                        g_metrics.ProfitTargetAmount,
                        g_sync_state.LastResponseBody);
           }
        }
     }

   ClearLocalFinalStateFromBackendContinue();

   bool backend_close_required = UseBackendRuleDecision && EnableSync && BackendRequiresClose();
   bool local_close_required = (g_state.Status == WF_STATUS_FAILED || g_state.TradingBlocked) && LocalRulesAreAuthoritative();

   if(backend_close_required || local_close_required)
     {
      string last_trade_action = g_last_action;
      string close_reason = backend_close_required
         ? BackendStringFieldValue("ea_action_reason","backend_requested_close")
         : g_state.BreachReason;
      if(StringLen(close_reason) == 0)
         close_reason = backend_close_required ? "Backend requested close" : ((g_state.Status == WF_STATUS_FAILED) ? "Local EA status failed" : "Local EA trading block");
      string close_source = backend_close_required ? "backend_api_response" : "local_ea_rule_engine";
      bool close_positions_required = backend_close_required
         ? BackendBoolFieldTrue("close_positions_required")
         : false;
      bool mt5_deactivation_required = backend_close_required
         ? BackendBoolFieldTrue("mt5_deactivation_required")
         : false;
      bool trading_blocked = backend_close_required
         ? BackendBoolFieldTrue("trading_blocked")
         : g_state.TradingBlocked;

      PrintFormat("Wolforix Close Decision: source=%s reason=%s status=%s trading_blocked=%s close_positions_required=%s mt5_deactivation_required=%s diagnostic_only=%s positions=%d backend_response_body=%s",
                  close_source,
                  close_reason,
                  WFEngineStatusText(g_state.Status),
                  WFYesNo(trading_blocked),
                  WFYesNo(close_positions_required),
                  WFYesNo(mt5_deactivation_required),
                  WFYesNo(DiagnosticOnly),
                  PositionsTotal(),
                  g_sync_state.LastResponseBody);

      bool positions_ok = WFCloseAllPositions(g_trade,
                                              g_close_report,
                                              last_trade_action,
                                              CloseRetryAttempts,
                                              CloseRetryDelayMilliseconds,
                                              DiagnosticOnly,
                                              close_reason,
                                              close_source,
                                              trading_blocked,
                                              close_positions_required,
                                              mt5_deactivation_required,
                                              g_sync_state.LastResponseBody);
      bool orders_ok    = WFDeleteAllPendingOrders(g_trade,
                                                   last_trade_action,
                                                   DiagnosticOnly,
                                                   close_reason,
                                                   close_source);

      if(last_trade_action != g_last_action)
        {
         g_last_action = last_trade_action;
         MarkStateDirty();
        }

      if(!positions_ok || !orders_ok)
        {
         g_state.TradingBlocked = false;
         g_last_action = "Close pending; retrying before trading block";
         MarkStateDirty();
        }
      else if(!g_state.TradingBlocked)
        {
         g_state.TradingBlocked = true;
         g_last_action = "Positions closed; trading block confirmed";
         MarkStateDirty();
        }
     }

   g_state.LastEvaluationTime = now;
   RefreshChartStatusPanel(source);

   PersistStateIfNeeded(false);
  }

int OnInit()
  {
   WFPopulateRuleSet(InpChallengePreset,g_rules);
   WFEnsureStorageFolders();
   WFResetSyncState(g_sync_state);
   WFResetCloseReport(g_close_report);
   g_sync_state.Enabled = EnableSync;
   if(EnableSync)
      g_sync_state.LastResult = "Pending";

   string challenge_id = WFSanitizeKey(InpChallengeId);
   long login          = (long)AccountInfoInteger(ACCOUNT_LOGIN);
   g_state_file_path   = WFStateFilePath(login,challenge_id);
   g_snapshot_file_path= WFSnapshotFilePath(login,challenge_id);

   if(InpResetStoredState)
     {
      WFDeleteStateFile(g_state_file_path);
      if(InpWriteBridgeSnapshot)
         WFDeleteStateFile(g_snapshot_file_path);
     }

   bool loaded = WFLoadState(g_state_file_path,g_state);
   if(!loaded ||
      g_state.AccountLogin != login ||
      g_state.PresetCode != g_rules.Code ||
      g_state.ChallengeId != challenge_id ||
      g_state.Version != WF_ENGINE_VERSION)
     {
      double balance = AccountInfoDouble(ACCOUNT_BALANCE);
      double equity  = AccountInfoDouble(ACCOUNT_EQUITY);
      double initial_balance = balance;
      if(InpInitialBalanceOverride > 0.0)
         initial_balance = InpInitialBalanceOverride;

      WFInitializeState(g_state,login,challenge_id,g_rules,initial_balance,equity,WFServerNow());
      g_state.DayStartBalance = balance;
      g_state.DayStartEquity  = equity;
      g_state.DayHighestEquity= equity;
      g_state.PeakBalance     = balance;
      g_state.PeakEquity      = equity;
      g_last_action           = "Fresh runtime state created";
      g_state_dirty           = true;
     }
   else
     {
      g_last_action = "Persisted runtime state loaded";
     }

   g_trade.SetAsyncMode(false);
   EventSetTimer((InpTimerIntervalSeconds < 1) ? 1 : InpTimerIntervalSeconds);
   g_refresh_trading_days = true;

   EvaluateEngine("init");
   PersistStateIfNeeded(true);
   MaybeSyncMetrics(true,"init");
   RefreshChartStatusPanel("init");
   return(INIT_SUCCEEDED);
  }

void OnDeinit(const int reason)
  {
   PersistStateIfNeeded(true);
   EventKillTimer();
   Comment("");
  }

void OnTick()
  {
   EvaluateEngine("tick");
   MaybeSyncFloatingState();
   RefreshChartStatusPanel("tick");
  }

void OnTimer()
  {
   EvaluateEngine("timer");
   MaybeSyncMetricsWithPolicy(false,"timer",SyncIntervalSeconds,false);
   RefreshChartStatusPanel("timer");
  }

void OnTradeTransaction(const MqlTradeTransaction &trans,
                        const MqlTradeRequest &request,
                        const MqlTradeResult &result)
  {
   g_refresh_trading_days = true;
   string trigger = TradeSyncTrigger(trans);
   EvaluateEngine(trigger);
   MaybeSyncMetricsWithPolicy(false,trigger,TradeEventSyncCooldownSeconds,true);
   RefreshChartStatusPanel(trigger);
  }
