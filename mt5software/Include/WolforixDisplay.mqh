#ifndef __WOLFORIX_DISPLAY_MQH__
#define __WOLFORIX_DISPLAY_MQH__

#include "WolforixTypes.mqh"

string WFBuildChartStatus(const string challenge_id,
                          const string currency,
                          const string server_now_text,
                          const WFRuleSet &rules,
                          const WFRuntimeState &state,
                          const WFMetrics &metrics,
                          const WFSyncState &sync_state,
                          const WFDailyLossMode daily_loss_mode,
                          const string last_action)
  {
   string daily_reference_label = "Day start balance";
   bool passed_conditions = (state.Status == WF_STATUS_PASSED || metrics.Passed);
   if(daily_loss_mode == WF_DAILY_LOSS_FROM_DAY_HIGH_EQUITY)
      daily_reference_label = "Day highest equity";

   string text;
   text += "Wolforix Rule Engine\n";
   text += "Challenge: " + challenge_id + " | Preset: " + rules.Label + "\n";
   text += "Status: " + WFEngineStatusText(state.Status) + " | Trading Blocked: " + WFYesNo(state.TradingBlocked) + "\n";
   text += "Server Time: " + server_now_text + "\n";
   text += "Balance: " + WFFmtMoney(metrics.Balance) + " " + currency;
   text += " | Equity: " + WFFmtMoney(metrics.Equity) + " " + currency;
   text += " | Float: " + WFFmtMoney(metrics.FloatingPnL) + " " + currency + "\n";
   text += "Initial Balance: " + WFFmtMoney(state.InitialBalance) + " " + currency;
   text += " | Challenge Start: " + WFFmtDateTime(state.ChallengeStartTime) + "\n";
   text += "Day Start Balance: " + WFFmtMoney(state.DayStartBalance) + " " + currency;
   text += " | Day Start Equity: " + WFFmtMoney(state.DayStartEquity) + " " + currency + "\n";
   text += "Day Highest Equity: " + WFFmtMoney(state.DayHighestEquity) + " " + currency;
   text += " | " + daily_reference_label + ": " + WFFmtMoney(metrics.DailyLossReference) + " " + currency + "\n";
   text += "Daily Loss: " + WFFmtMoney(metrics.DailyLossAmount) + " / " + WFFmtMoney(metrics.DailyLossLimitAmount) + " " + currency;
   text += " | Remaining: " + WFFmtMoney(metrics.DailyLossRemaining) + " " + currency + "\n";
   text += "Max Drawdown: " + WFFmtMoney(metrics.MaxDrawdownAmount) + " / " + WFFmtMoney(metrics.MaxDrawdownLimitAmount) + " " + currency;
   text += " | Remaining: " + WFFmtMoney(metrics.MaxDrawdownRemaining) + " " + currency + "\n";
   text += "Profit Target: " + WFFmtMoney(metrics.ProfitAmount) + " / " + WFFmtMoney(metrics.ProfitTargetAmount) + " " + currency;
   text += " | Remaining: " + WFFmtMoney(metrics.ProfitRemaining) + " " + currency + "\n";
   text += "Trading Days: " + IntegerToString(metrics.TradingDaysCount) + " / " + IntegerToString(rules.MinTradingDays);
   text += " | Target Hit: " + WFYesNo(metrics.ProfitTargetReached) + "\n";
   text += "Passed Conditions: " + WFYesNo(passed_conditions);
   text += " | Last Action: " + last_action + "\n";
   text += "Reason: " + state.BreachReason + "\n";
   text += "Sync Enabled: " + WFYesNo(sync_state.Enabled);
   text += " | Last Sync: " + WFFmtDateTime(sync_state.LastAttemptTime) + "\n";
   text += "Sync Result: " + sync_state.LastResult;
   text += " | HTTP: " + WFHttpCodeText(sync_state.LastHttpCode);

   return text;
  }

#endif
