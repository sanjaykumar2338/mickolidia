#ifndef __WOLFORIX_ENGINE_MQH__
#define __WOLFORIX_ENGINE_MQH__

#include <Trade/Trade.mqh>
#include "WolforixTypes.mqh"

#define WF_ENGINE_VERSION 1
#define WF_TRADE_RETCODE_CLIENT_DISABLES_AT 10027
#define WF_ERROR_TRADE_DISABLED 4752

datetime WFServerNow()
  {
   datetime server_now = TimeTradeServer();
   if(server_now <= 0)
      server_now = TimeCurrent();
   return server_now;
  }

int WFDayKey(const datetime value)
  {
   if(value <= 0)
      return 0;

   MqlDateTime parts;
   TimeToStruct(value,parts);
   return parts.year * 10000 + parts.mon * 100 + parts.day;
  }

datetime WFDayStart(const datetime value)
  {
   if(value <= 0)
      return 0;

   MqlDateTime parts;
   TimeToStruct(value,parts);
   parts.hour = 0;
   parts.min  = 0;
   parts.sec  = 0;
   return StructToTime(parts);
  }

bool WFIsTradeDealType(const ENUM_DEAL_TYPE deal_type)
  {
   return (deal_type == DEAL_TYPE_BUY || deal_type == DEAL_TYPE_SELL);
  }

bool WFIsTradeEntryType(const ENUM_DEAL_ENTRY deal_entry)
  {
   return (deal_entry == DEAL_ENTRY_IN ||
           deal_entry == DEAL_ENTRY_OUT ||
           deal_entry == DEAL_ENTRY_INOUT ||
           deal_entry == DEAL_ENTRY_OUT_BY);
  }

bool WFEnsureStorageFolders()
  {
   FolderCreate("Wolforix",FILE_COMMON);
   FolderCreate("Wolforix\\RuleEngine",FILE_COMMON);
   FolderCreate("Wolforix\\RuleEngine\\States",FILE_COMMON);
   FolderCreate("Wolforix\\RuleEngine\\Snapshots",FILE_COMMON);
   return true;
  }

string WFStateFilePath(const long login,const string challenge_id)
  {
   string login_str = StringFormat("%I64d",login);
   return "Wolforix\\RuleEngine\\States\\state_" + login_str + "_" + WFSanitizeKey(challenge_id) + ".ini";
  }

string WFSnapshotFilePath(const long login,const string challenge_id)
  {
   string login_str = StringFormat("%I64d",login);
   return "Wolforix\\RuleEngine\\Snapshots\\snapshot_" + login_str + "_" + WFSanitizeKey(challenge_id) + ".ini";
  }

void WFResetRuntimeState(WFRuntimeState &state)
  {
   state.Version             = WF_ENGINE_VERSION;
   state.AccountLogin        = 0;
   state.ChallengeId         = "";
   state.PresetCode          = "";
   state.Status              = WF_STATUS_ACTIVE;
   state.TradingBlocked      = false;
   state.ChallengeStartTime  = 0;
   state.PassedAt            = 0;
   state.FailedAt            = 0;
   state.BreachReason        = "";
   state.InitialBalance      = 0.0;
   state.InitialEquity       = 0.0;
   state.CurrentDayKey       = 0;
   state.CurrentDayOpenTime  = 0;
   state.DayStartBalance     = 0.0;
   state.DayStartEquity      = 0.0;
   state.DayHighestEquity    = 0.0;
   state.PeakBalance         = 0.0;
   state.PeakEquity          = 0.0;
   state.LastTradingDayCount = 0;
   state.LastEvaluationTime  = 0;
  }

bool WFDeleteStateFile(const string path)
  {
   ResetLastError();
   if(FileIsExist(path,FILE_COMMON))
      return FileDelete(path,FILE_COMMON);
   return true;
  }

bool WFLoadState(const string path,WFRuntimeState &state)
  {
   if(!FileIsExist(path,FILE_COMMON))
      return false;

   int handle = FileOpen(path,FILE_READ | FILE_TXT | FILE_COMMON | FILE_ANSI);
   if(handle == INVALID_HANDLE)
     {
      PrintFormat("Wolforix: failed to open state file '%s' (error %d)",path,GetLastError());
      return false;
     }

   WFResetRuntimeState(state);

   while(!FileIsEnding(handle))
     {
      string line = FileReadString(handle);
      StringTrimLeft(line);
      StringTrimRight(line);
      if(StringLen(line) == 0)
         continue;

      int separator = StringFind(line,"=");
      if(separator < 0)
         continue;

      string key   = StringSubstr(line,0,separator);
      string value = StringSubstr(line,separator + 1);
      StringTrimLeft(key);
      StringTrimRight(key);
      StringTrimLeft(value);
      StringTrimRight(value);

      if(key == "version")
         state.Version = (int)StringToInteger(value);
      else if(key == "account_login")
         state.AccountLogin = (long)StringToInteger(value);
      else if(key == "challenge_id")
         state.ChallengeId = value;
      else if(key == "preset_code")
         state.PresetCode = value;
      else if(key == "status")
         state.Status = (int)StringToInteger(value);
      else if(key == "trading_blocked")
         state.TradingBlocked = (StringToInteger(value) == 1);
      else if(key == "challenge_start")
         state.ChallengeStartTime = WFParseDateTime(value);
      else if(key == "passed_at")
         state.PassedAt = WFParseDateTime(value);
      else if(key == "failed_at")
         state.FailedAt = WFParseDateTime(value);
      else if(key == "breach_reason")
         state.BreachReason = value;
      else if(key == "initial_balance")
         state.InitialBalance = StringToDouble(value);
      else if(key == "initial_equity")
         state.InitialEquity = StringToDouble(value);
      else if(key == "current_day_key")
         state.CurrentDayKey = (int)StringToInteger(value);
      else if(key == "current_day_open")
         state.CurrentDayOpenTime = WFParseDateTime(value);
      else if(key == "day_start_balance")
         state.DayStartBalance = StringToDouble(value);
      else if(key == "day_start_equity")
         state.DayStartEquity = StringToDouble(value);
      else if(key == "day_highest_equity")
         state.DayHighestEquity = StringToDouble(value);
      else if(key == "peak_balance")
         state.PeakBalance = StringToDouble(value);
      else if(key == "peak_equity")
         state.PeakEquity = StringToDouble(value);
      else if(key == "last_trading_day_count")
         state.LastTradingDayCount = (int)StringToInteger(value);
      else if(key == "last_evaluation")
         state.LastEvaluationTime = WFParseDateTime(value);
     }

   FileClose(handle);

   return true;
  }

bool WFSaveState(const string path,const WFRuntimeState &state)
  {
   WFEnsureStorageFolders();

   int handle = FileOpen(path,FILE_WRITE | FILE_TXT | FILE_COMMON | FILE_ANSI);
   if(handle == INVALID_HANDLE)
     {
      PrintFormat("Wolforix: failed to write state file '%s' (error %d)",path,GetLastError());
      return false;
     }

   string content;
   string account_login_str = StringFormat("%I64d",state.AccountLogin);
   content += "version=" + IntegerToString(state.Version) + "\n";
   content += "account_login=" + account_login_str + "\n";
   content += "challenge_id=" + state.ChallengeId + "\n";
   content += "preset_code=" + state.PresetCode + "\n";
   content += "status=" + IntegerToString(state.Status) + "\n";
   content += "trading_blocked=" + IntegerToString((int)state.TradingBlocked) + "\n";
   content += "challenge_start=" + WFSerializeDateTime(state.ChallengeStartTime) + "\n";
   content += "passed_at=" + WFSerializeDateTime(state.PassedAt) + "\n";
   content += "failed_at=" + WFSerializeDateTime(state.FailedAt) + "\n";
   content += "breach_reason=" + state.BreachReason + "\n";
   content += "initial_balance=" + DoubleToString(state.InitialBalance,2) + "\n";
   content += "initial_equity=" + DoubleToString(state.InitialEquity,2) + "\n";
   content += "current_day_key=" + IntegerToString(state.CurrentDayKey) + "\n";
   content += "current_day_open=" + WFSerializeDateTime(state.CurrentDayOpenTime) + "\n";
   content += "day_start_balance=" + DoubleToString(state.DayStartBalance,2) + "\n";
   content += "day_start_equity=" + DoubleToString(state.DayStartEquity,2) + "\n";
   content += "day_highest_equity=" + DoubleToString(state.DayHighestEquity,2) + "\n";
   content += "peak_balance=" + DoubleToString(state.PeakBalance,2) + "\n";
   content += "peak_equity=" + DoubleToString(state.PeakEquity,2) + "\n";
   content += "last_trading_day_count=" + IntegerToString(state.LastTradingDayCount) + "\n";
   content += "last_evaluation=" + WFSerializeDateTime(state.LastEvaluationTime) + "\n";

   uint bytes_written = FileWriteString(handle,content);
   FileClose(handle);

   if(bytes_written == 0)
     {
      PrintFormat("Wolforix: state file write produced 0 bytes for '%s' (error %d)",path,GetLastError());
      return false;
     }

   return true;
  }

bool WFWriteSnapshot(const string path,const WFRuntimeState &state,const WFRuleSet &rules,const WFMetrics &metrics,const WFDailyLossMode daily_loss_mode)
  {
   WFEnsureStorageFolders();

   int handle = FileOpen(path,FILE_WRITE | FILE_TXT | FILE_COMMON | FILE_ANSI);
   if(handle == INVALID_HANDLE)
     {
      PrintFormat("Wolforix: failed to write snapshot file '%s' (error %d)",path,GetLastError());
      return false;
     }

   string content;
   string account_login_str = StringFormat("%I64d",state.AccountLogin);
   content += "engine_version=" + IntegerToString(WF_ENGINE_VERSION) + "\n";
   content += "account_login=" + account_login_str + "\n";
   content += "challenge_id=" + state.ChallengeId + "\n";
   content += "preset=" + rules.Label + "\n";
   content += "preset_code=" + rules.Code + "\n";
   content += "status=" + WFEngineStatusText(state.Status) + "\n";
   content += "trading_blocked=" + WFYesNo(state.TradingBlocked) + "\n";
   content += "challenge_start=" + WFFmtDateTime(state.ChallengeStartTime) + "\n";
   content += "last_evaluation=" + WFFmtDateTime(state.LastEvaluationTime) + "\n";
   content += "daily_loss_mode=" + WFDailyLossModeText(daily_loss_mode) + "\n";
   content += "balance=" + DoubleToString(metrics.Balance,2) + "\n";
   content += "equity=" + DoubleToString(metrics.Equity,2) + "\n";
   content += "floating_pnl=" + DoubleToString(metrics.FloatingPnL,2) + "\n";
   content += "initial_balance=" + DoubleToString(state.InitialBalance,2) + "\n";
   content += "initial_equity=" + DoubleToString(state.InitialEquity,2) + "\n";
   content += "day_start_balance=" + DoubleToString(state.DayStartBalance,2) + "\n";
   content += "day_start_equity=" + DoubleToString(state.DayStartEquity,2) + "\n";
   content += "day_highest_equity=" + DoubleToString(state.DayHighestEquity,2) + "\n";
   content += "peak_balance=" + DoubleToString(state.PeakBalance,2) + "\n";
   content += "peak_equity=" + DoubleToString(state.PeakEquity,2) + "\n";
   content += "daily_loss_reference=" + DoubleToString(metrics.DailyLossReference,2) + "\n";
   content += "daily_loss_amount=" + DoubleToString(metrics.DailyLossAmount,2) + "\n";
   content += "daily_loss_limit=" + DoubleToString(metrics.DailyLossLimitAmount,2) + "\n";
   content += "daily_loss_remaining=" + DoubleToString(metrics.DailyLossRemaining,2) + "\n";
   content += "max_drawdown_amount=" + DoubleToString(metrics.MaxDrawdownAmount,2) + "\n";
   content += "max_drawdown_limit=" + DoubleToString(metrics.MaxDrawdownLimitAmount,2) + "\n";
   content += "max_drawdown_remaining=" + DoubleToString(metrics.MaxDrawdownRemaining,2) + "\n";
   content += "profit_amount=" + DoubleToString(metrics.ProfitAmount,2) + "\n";
   content += "profit_target_amount=" + DoubleToString(metrics.ProfitTargetAmount,2) + "\n";
   content += "profit_remaining=" + DoubleToString(metrics.ProfitRemaining,2) + "\n";
   content += "trading_days_count=" + IntegerToString(metrics.TradingDaysCount) + "\n";
   content += "min_trading_days=" + IntegerToString(rules.MinTradingDays) + "\n";
   content += "profit_target_reached=" + WFYesNo(metrics.ProfitTargetReached) + "\n";
   content += "min_trading_days_reached=" + WFYesNo(metrics.MinTradingDaysReached) + "\n";
   content += "daily_loss_breached=" + WFYesNo(metrics.DailyLossBreached) + "\n";
   content += "max_drawdown_breached=" + WFYesNo(metrics.MaxDrawdownBreached) + "\n";
   content += "passed=" + WFYesNo(metrics.Passed) + "\n";
   content += "breach_reason=" + state.BreachReason + "\n";
   content += "passed_at=" + WFFmtDateTime(state.PassedAt) + "\n";
   content += "failed_at=" + WFFmtDateTime(state.FailedAt) + "\n";

   uint bytes_written = FileWriteString(handle,content);
   FileClose(handle);

   if(bytes_written == 0)
     {
      PrintFormat("Wolforix: snapshot write produced 0 bytes for '%s' (error %d)",path,GetLastError());
      return false;
     }

   return true;
  }

int WFCountTradingDaysFromHistory(const datetime challenge_start,const datetime now)
  {
   if(challenge_start <= 0 || now <= 0)
      return 0;

   if(!HistorySelect(challenge_start,now))
     {
      PrintFormat("Wolforix: HistorySelect failed while counting trading days (error %d)",GetLastError());
      return -1;
     }

   int unique_days[];
   int unique_count = 0;
   const int total = (int)HistoryDealsTotal();
   for(int i = 0; i < total; ++i)
     {
      ulong ticket = HistoryDealGetTicket(i);
      if(ticket == 0)
         continue;

      ENUM_DEAL_TYPE deal_type = (ENUM_DEAL_TYPE)HistoryDealGetInteger(ticket,DEAL_TYPE);
      if(!WFIsTradeDealType(deal_type))
         continue;

      ENUM_DEAL_ENTRY deal_entry = (ENUM_DEAL_ENTRY)HistoryDealGetInteger(ticket,DEAL_ENTRY);
      if(!WFIsTradeEntryType(deal_entry))
         continue;

      datetime deal_time = (datetime)HistoryDealGetInteger(ticket,DEAL_TIME);
      if(deal_time < challenge_start)
         continue;

      int day_key = WFDayKey(deal_time);
      bool exists = false;
      for(int j = 0; j < unique_count; ++j)
        {
         if(unique_days[j] == day_key)
           {
            exists = true;
            break;
           }
        }

      if(exists)
         continue;

      ArrayResize(unique_days,unique_count + 1);
      unique_days[unique_count] = day_key;
      unique_count++;
     }

   return unique_count;
  }

void WFInitializeState(WFRuntimeState &state,const long login,const string challenge_id,const WFRuleSet &rules,const double balance,const double equity,const datetime now)
  {
   WFResetRuntimeState(state);

   state.Version             = WF_ENGINE_VERSION;
   state.AccountLogin        = login;
   state.ChallengeId         = challenge_id;
   state.PresetCode          = rules.Code;
   state.Status              = WF_STATUS_ACTIVE;
   state.TradingBlocked      = false;
   state.ChallengeStartTime  = now;
   state.InitialBalance      = balance;
   state.InitialEquity       = equity;
   state.CurrentDayKey       = WFDayKey(now);
   state.CurrentDayOpenTime  = WFDayStart(now);
   state.DayStartBalance     = balance;
   state.DayStartEquity      = equity;
   state.DayHighestEquity    = equity;
   state.PeakBalance         = balance;
   state.PeakEquity          = equity;
   state.LastTradingDayCount = 0;
   state.LastEvaluationTime  = now;
  }

void WFEnsureDailySession(WFRuntimeState &state,const datetime now,const double balance,const double equity,bool &day_reset)
  {
   day_reset = false;

   int current_day_key = WFDayKey(now);
   if(state.CurrentDayKey == 0)
     {
      state.CurrentDayKey      = current_day_key;
      state.CurrentDayOpenTime = WFDayStart(now);
      state.DayStartBalance    = balance;
      state.DayStartEquity     = equity;
      state.DayHighestEquity   = equity;
      return;
     }

   if(current_day_key != state.CurrentDayKey)
     {
      state.CurrentDayKey      = current_day_key;
      state.CurrentDayOpenTime = WFDayStart(now);
      state.DayStartBalance    = balance;
      state.DayStartEquity     = equity;
      state.DayHighestEquity   = equity;
      day_reset                = true;
     }
  }

bool WFRefreshPeaks(WFRuntimeState &state,const double balance,const double equity)
  {
   bool changed = false;

   if(balance > state.PeakBalance)
     {
      state.PeakBalance = balance;
      changed = true;
     }

   if(equity > state.PeakEquity)
     {
      state.PeakEquity = equity;
      changed = true;
     }

   if(equity > state.DayHighestEquity)
     {
      state.DayHighestEquity = equity;
      changed = true;
     }

   return changed;
  }

void WFComputeMetrics(const WFRuleSet &rules,
                      const WFDailyLossMode daily_loss_mode,
                      const WFRuntimeState &state,
                      const double balance,
                      const double equity,
                      const int trading_days_count,
                      WFMetrics &metrics)
  {
   metrics.Balance              = balance;
   metrics.Equity               = equity;
   metrics.FloatingPnL          = equity - balance;
   metrics.ProfitAmount         = balance - state.InitialBalance;
   metrics.ProfitTargetAmount   = state.InitialBalance * (rules.ProfitTargetPct / 100.0);
   metrics.ProfitRemaining      = MathMax(0.0,metrics.ProfitTargetAmount - metrics.ProfitAmount);
   metrics.ProfitTargetReached  = (metrics.ProfitAmount + 0.0000001 >= metrics.ProfitTargetAmount);

   metrics.DailyLossReference = state.DayStartBalance;
   if(daily_loss_mode == WF_DAILY_LOSS_FROM_DAY_HIGH_EQUITY)
      metrics.DailyLossReference = state.DayHighestEquity;

   metrics.DailyLossLimitAmount = state.InitialBalance * (rules.DailyLossPct / 100.0);
   metrics.DailyLossAmount      = MathMax(0.0,metrics.DailyLossReference - equity);
   metrics.DailyLossRemaining   = MathMax(0.0,metrics.DailyLossLimitAmount - metrics.DailyLossAmount);
   metrics.DailyLossBreached    = (metrics.DailyLossAmount + 0.0000001 >= metrics.DailyLossLimitAmount);

   metrics.DrawdownFloor         = MathMin(balance,equity);
   metrics.MaxDrawdownLimitAmount= state.InitialBalance * (rules.MaxDrawdownPct / 100.0);
   metrics.MaxDrawdownAmount     = MathMax(0.0,state.InitialBalance - metrics.DrawdownFloor);
   metrics.MaxDrawdownRemaining  = MathMax(0.0,metrics.MaxDrawdownLimitAmount - metrics.MaxDrawdownAmount);
   metrics.MaxDrawdownBreached   = (metrics.MaxDrawdownAmount + 0.0000001 >= metrics.MaxDrawdownLimitAmount);

   metrics.TradingDaysCount      = trading_days_count;
   metrics.MinTradingDaysReached = (trading_days_count >= rules.MinTradingDays);
   metrics.Passed                = (metrics.ProfitTargetReached && metrics.MinTradingDaysReached &&
                                    !metrics.DailyLossBreached && !metrics.MaxDrawdownBreached);
  }

void WFAppendJsonString(string &payload,bool &first,const string value)
  {
   if(!first)
      payload += ",";
   first = false;

   string escaped = value;
   StringReplace(escaped,"\\","\\\\");
   StringReplace(escaped,"\"","\\\"");
   StringReplace(escaped,"\r","\\r");
   StringReplace(escaped,"\n","\\n");
   StringReplace(escaped,"\t","\\t");
   payload += "\"" + escaped + "\"";
  }

void WFCollectRemainingCloseTickets(string &tickets_json,int &remaining_count)
  {
   tickets_json = "[";
   bool first = true;
   remaining_count = 0;

   for(int i = PositionsTotal() - 1; i >= 0; --i)
     {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0)
         continue;

      if(!PositionSelectByTicket(ticket))
         continue;

      WFAppendJsonString(tickets_json,first,StringFormat("%I64u",ticket));
      remaining_count++;
     }

   tickets_json += "]";
  }

bool WFCloseAllPositions(CTrade &trade,
                         WFCloseReport &report,
                         string &last_action,
                         const int retry_attempts,
                         const int retry_delay_ms,
                         const bool diagnostic_only,
                         const string close_reason,
                         const string close_source,
                         const bool trading_blocked,
                         const bool close_positions_required,
                         const bool mt5_deactivation_required,
                         const string backend_response_body)
  {
   WFResetCloseReport(report);
   int attempts = MathMax(retry_attempts,1);
   int delay_ms = MathMax(retry_delay_ms,250);
   int initial_positions = PositionsTotal();

   report.Attempted = (initial_positions > 0);
   if(initial_positions <= 0)
     {
      report.Success = true;
      report.LastMessage = "No open positions to close";
      return true;
     }

   string reasons_json = "[";
   bool first_reason = true;
   string closed_tickets_json = "[";
   bool first_closed_ticket = true;
   string closed_identifiers_json = "[";
   bool first_closed_identifier = true;

   for(int attempt = 1; attempt <= attempts; ++attempt)
     {
      int positions_before_attempt = PositionsTotal();
      if(positions_before_attempt <= 0)
         break;

      for(int i = PositionsTotal() - 1; i >= 0; --i)
        {
         ulong ticket = PositionGetTicket(i);
         if(ticket == 0)
            continue;

         if(!PositionSelectByTicket(ticket))
            continue;

         string symbol = PositionGetString(POSITION_SYMBOL);
         double volume = PositionGetDouble(POSITION_VOLUME);
         long position_identifier = (long)PositionGetInteger(POSITION_IDENTIFIER);
         long position_ticket = (long)PositionGetInteger(POSITION_TICKET);
         ENUM_POSITION_TYPE position_type = (ENUM_POSITION_TYPE)PositionGetInteger(POSITION_TYPE);
         int digits = (int)SymbolInfoInteger(symbol,SYMBOL_DIGITS);
         double close_price = 0.0;
         if(position_type == POSITION_TYPE_BUY)
            close_price = SymbolInfoDouble(symbol,SYMBOL_BID);
         else if(position_type == POSITION_TYPE_SELL)
            close_price = SymbolInfoDouble(symbol,SYMBOL_ASK);

         PrintFormat("Wolforix AutoClose Triggered: reason=%s source=%s ticket=%I64u symbol=%s volume=%.2f trading_blocked=%s close_positions_required=%s mt5_deactivation_required=%s diagnostic_only=%s server_response_body=%s",
                     close_reason,
                     close_source,
                     ticket,
                     symbol,
                     volume,
                     WFYesNo(trading_blocked),
                     WFYesNo(close_positions_required),
                     WFYesNo(mt5_deactivation_required),
                     WFYesNo(diagnostic_only),
                     backend_response_body);

         if(diagnostic_only)
           {
            string diagnostic_reason = StringFormat("diagnostic_only=true; would_close ticket=%I64u symbol=%s volume=%.2f close_price=%.*f reason=%s source=%s",
                                                    ticket,
                                                    symbol,
                                                    volume,
                                                    digits,
                                                    close_price,
                                                    close_reason,
                                                    close_source);
            WFAppendJsonString(reasons_json,first_reason,diagnostic_reason);
            last_action = "DiagnosticOnly: would close " + symbol + " #" + StringFormat("%I64u",ticket);
            PrintFormat("Wolforix DiagnosticOnly: NOT closing ticket=%I64u symbol=%s volume=%.2f reason=%s source=%s",
                        ticket,
                        symbol,
                        volume,
                        close_reason,
                        close_source);
            continue;
           }

         ResetLastError();
         bool closed = trade.PositionClose(ticket);
         uint retcode = trade.ResultRetcode();
         int error_code = GetLastError();
         string result_message = trade.ResultRetcodeDescription();

         PrintFormat("Wolforix Close: attempt=%d ticket=%I64u symbol=%s volume=%.2f close_price=%.*f retcode=%u error=%d message=%s",
                     attempt,
                     ticket,
                     symbol,
                     volume,
                     digits,
                     close_price,
                     retcode,
                     error_code,
                     result_message);

         if(closed)
           {
            string ticket_str = StringFormat("%I64u",ticket);
            WFAppendJsonString(closed_tickets_json,first_closed_ticket,ticket_str);
            if(position_ticket > 0 && (ulong)position_ticket != ticket)
               WFAppendJsonString(closed_tickets_json,first_closed_ticket,StringFormat("%I64d",position_ticket));
            if(position_identifier > 0)
               WFAppendJsonString(closed_identifiers_json,first_closed_identifier,StringFormat("%I64d",position_identifier));
            report.ClosedPositionsCount++;
            last_action = "Closed position " + symbol + " #" + ticket_str;
            continue;
           }

         report.FailedCloseCount++;
         string reason = StringFormat("ticket=%I64u symbol=%s volume=%.2f close_price=%.*f retcode=%u error=%d message=%s",
                                      ticket,
                                      symbol,
                                      volume,
                                      digits,
                                      close_price,
                                      retcode,
                                      error_code,
                                      result_message);
         WFAppendJsonString(reasons_json,first_reason,reason);
         last_action = "Close failed retcode=" + IntegerToString((int)retcode);

         if(retcode == WF_TRADE_RETCODE_CLIENT_DISABLES_AT || error_code == WF_ERROR_TRADE_DISABLED)
           {
            Print("Wolforix Close: Cannot close positions because MT5 AutoTrading is disabled. Enable Algo Trading to allow EA to close positions.");
            report.LastMessage = "Cannot close positions because MT5 AutoTrading is disabled. Enable Algo Trading to allow EA to close positions.";
           }
        }

      if(PositionsTotal() <= 0)
         break;

      if(attempt < attempts)
        {
         PrintFormat("Wolforix Close: retrying remaining positions after %d ms",delay_ms);
         Sleep(delay_ms);
        }
     }

   closed_tickets_json += "]";
   closed_identifiers_json += "]";
   reasons_json += "]";
   report.ClosedPositionTicketsJson = closed_tickets_json;
   report.ClosedPositionIdentifiersJson = closed_identifiers_json;
   report.CloseFailedReasonsJson = reasons_json;
   WFCollectRemainingCloseTickets(report.FailedCloseTicketsJson,report.PositionsRemainingCount);
   report.Success = (report.PositionsRemainingCount == 0);

   if(diagnostic_only)
      report.LastMessage = "DiagnosticOnly enabled; no positions were closed";
   else if(report.Success)
      report.LastMessage = "All open positions closed successfully before final trading block";
   else if(StringLen(report.LastMessage) == 0 || report.LastMessage == "No close attempt required")
      report.LastMessage = "Some open positions could not be closed before final trading block";

   return report.Success;
  }

bool WFDeleteAllPendingOrders(CTrade &trade,
                              string &last_action,
                              const bool diagnostic_only,
                              const string close_reason,
                              const string close_source)
  {
   bool all_ok = true;
   for(int i = OrdersTotal() - 1; i >= 0; --i)
     {
      ulong ticket = OrderGetTicket(i);
      if(ticket == 0)
         continue;

      if(!OrderSelect(ticket))
         continue;

      string symbol = OrderGetString(ORDER_SYMBOL);
      if(diagnostic_only)
        {
         PrintFormat("Wolforix DiagnosticOnly: NOT deleting pending order ticket=%I64u symbol=%s reason=%s source=%s",
                     ticket,
                     symbol,
                     close_reason,
                     close_source);
         last_action = "DiagnosticOnly: would delete pending order " + symbol + " #" + StringFormat("%I64u",ticket);
         all_ok = false;
         continue;
        }

      if(trade.OrderDelete(ticket))
        {
         string ticket_str = StringFormat("%I64u",ticket);
         last_action = "Deleted pending order " + symbol + " #" + ticket_str;
        }
      else
        {
         all_ok = false;
         last_action = "Delete failed retcode=" + IntegerToString((int)trade.ResultRetcode());
         PrintFormat("Wolforix: failed to delete order #%I64u on %s (retcode %u, error %d)",ticket,symbol,trade.ResultRetcode(),GetLastError());
        }
     }
   return all_ok;
  }

void WFMarkFailure(WFRuntimeState &state,const datetime now,const string reason)
  {
   state.Status         = WF_STATUS_FAILED;
   state.TradingBlocked = false;
   state.BreachReason   = reason;
   state.FailedAt       = now;
  }

void WFMarkPassed(WFRuntimeState &state,const datetime now)
  {
   state.Status         = WF_STATUS_PASSED;
   state.PassedAt       = now;
   state.TradingBlocked = false;
   state.BreachReason   = "Profit target and minimum trading days completed";
  }

#endif
