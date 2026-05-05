#ifndef __WOLFORIX_SYNC_MQH__
#define __WOLFORIX_SYNC_MQH__

#include "WolforixTypes.mqh"

#define WF_SYNC_TIMEOUT_MS 10000
#define WF_SYNC_HISTORY_LOOKBACK_DAYS 90
#define WF_SYNC_MAX_CLOSED_TRADES 100

struct WFClosedTradeRow
  {
   long              PositionId;
   ulong             EntryDealTicket;
   ulong             ExitDealTicket;
   string            Symbol;
   string            Side;
   datetime          OpenTime;
   datetime          CloseTime;
   double            EntryPrice;
   double            ExitPrice;
   double            OpenVolume;
   double            ClosedVolume;
   double            Profit;
   double            Commission;
   double            Swap;
   bool              HasEntry;
   bool              HasExit;
  };

string WFTrimInputValue(string value)
  {
   StringTrimLeft(value);
   StringTrimRight(value);
   return value;
  }

string WFTrimTrailingSlash(string value)
  {
   value = WFTrimInputValue(value);

   while(StringLen(value) > 0)
     {
      int last_index = StringLen(value) - 1;
      if(StringGetCharacter(value,last_index) != '/')
         break;
      value = StringSubstr(value,0,last_index);
     }

   return value;
  }

string WFJsonEscape(string value)
  {
   StringReplace(value,"\\","\\\\");
   StringReplace(value,"\"","\\\"");
   StringReplace(value,"\r","\\r");
   StringReplace(value,"\n","\\n");
   StringReplace(value,"\t","\\t");
   return value;
  }

string WFJsonBool(const bool value)
  {
   return value ? "true" : "false";
  }

string WFJsonDateTimeValue(const datetime value)
  {
   if(value <= 0)
      return "null";

   return StringFormat("%I64d",(long)value);
  }

string WFJsonDoubleValue(const double value,const int digits)
  {
   return DoubleToString(value,digits);
  }

string WFJsonPriceValue(const string symbol,const double value)
  {
   int digits = (int)SymbolInfoInteger(symbol,SYMBOL_DIGITS);
   if(digits < 0)
      digits = 5;

   return DoubleToString(value,digits);
  }

bool WFDealEntryHasOpen(const ENUM_DEAL_ENTRY deal_entry)
  {
   return (deal_entry == DEAL_ENTRY_IN || deal_entry == DEAL_ENTRY_INOUT);
  }

bool WFDealEntryHasClose(const ENUM_DEAL_ENTRY deal_entry)
  {
   return (deal_entry == DEAL_ENTRY_OUT ||
           deal_entry == DEAL_ENTRY_OUT_BY ||
           deal_entry == DEAL_ENTRY_INOUT);
  }

bool WFSyncIsTradeDealType(const ENUM_DEAL_TYPE deal_type)
  {
   return (deal_type == DEAL_TYPE_BUY || deal_type == DEAL_TYPE_SELL);
  }

bool WFSyncIsTradeEntryType(const ENUM_DEAL_ENTRY deal_entry)
  {
   return (deal_entry == DEAL_ENTRY_IN ||
           deal_entry == DEAL_ENTRY_OUT ||
           deal_entry == DEAL_ENTRY_INOUT ||
           deal_entry == DEAL_ENTRY_OUT_BY);
  }

string WFSideFromPositionType(const ENUM_POSITION_TYPE position_type)
  {
   switch(position_type)
     {
      case POSITION_TYPE_BUY:
         return "buy";
      case POSITION_TYPE_SELL:
         return "sell";
      default:
         return "";
     }
  }

string WFSideFromDealType(const ENUM_DEAL_TYPE deal_type)
  {
   switch(deal_type)
     {
      case DEAL_TYPE_BUY:
         return "buy";
      case DEAL_TYPE_SELL:
         return "sell";
      default:
         return "";
     }
  }

string WFOppositeSide(const string side)
  {
   if(side == "buy")
      return "sell";

   if(side == "sell")
      return "buy";

   return "";
  }

bool WFPositionIdentifierOpen(const long position_id)
  {
   if(position_id <= 0)
      return false;

   for(int i = PositionsTotal() - 1; i >= 0; --i)
     {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0)
         continue;

      if(!PositionSelectByTicket(ticket))
         continue;

      long identifier = (long)PositionGetInteger(POSITION_IDENTIFIER);
      long position_ticket = (long)PositionGetInteger(POSITION_TICKET);

      if(identifier == position_id || position_ticket == position_id)
         return true;
     }

   return false;
  }

int WFFindClosedTradeRowByPosition(WFClosedTradeRow &rows[],const int row_count,const long position_id)
  {
   for(int i = 0; i < row_count; ++i)
     {
      if(rows[i].PositionId == position_id)
         return i;
     }

   return -1;
  }

void WFInitClosedTradeRow(WFClosedTradeRow &row,const long position_id)
  {
   row.PositionId      = position_id;
   row.EntryDealTicket = 0;
   row.ExitDealTicket  = 0;
   row.Symbol          = "";
   row.Side            = "";
   row.OpenTime        = 0;
   row.CloseTime       = 0;
   row.EntryPrice      = 0.0;
   row.ExitPrice       = 0.0;
   row.OpenVolume      = 0.0;
   row.ClosedVolume    = 0.0;
   row.Profit          = 0.0;
   row.Commission      = 0.0;
   row.Swap            = 0.0;
   row.HasEntry        = false;
   row.HasExit         = false;
  }

string WFBuildOpenPositionsJson(int &row_count)
  {
   row_count = 0;
   string payload = "[";
   bool first = true;

   for(int i = PositionsTotal() - 1; i >= 0; --i)
     {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0)
         continue;

      if(!PositionSelectByTicket(ticket))
         continue;

      string symbol = PositionGetString(POSITION_SYMBOL);
      if(StringLen(symbol) == 0)
         continue;

      long position_id = (long)PositionGetInteger(POSITION_IDENTIFIER);
      if(position_id <= 0)
         position_id = (long)PositionGetInteger(POSITION_TICKET);

      ENUM_POSITION_TYPE position_type = (ENUM_POSITION_TYPE)PositionGetInteger(POSITION_TYPE);
      datetime opened_at = (datetime)PositionGetInteger(POSITION_TIME);
      double entry_price = PositionGetDouble(POSITION_PRICE_OPEN);
      double current_price = PositionGetDouble(POSITION_PRICE_CURRENT);
      double volume = PositionGetDouble(POSITION_VOLUME);
      double profit = PositionGetDouble(POSITION_PROFIT);
      double swap = PositionGetDouble(POSITION_SWAP);

      if(!first)
         payload += ",";
      first = false;

      payload += "{";
      payload += "\"position_id\":" + StringFormat("%I64d",position_id);
      payload += ",\"ticket\":" + StringFormat("%I64u",ticket);
      payload += ",\"symbol\":\"" + WFJsonEscape(symbol) + "\"";
      payload += ",\"trade_side\":\"" + WFJsonEscape(WFSideFromPositionType(position_type)) + "\"";
      payload += ",\"open_timestamp\":" + WFJsonDateTimeValue(opened_at);
      payload += ",\"entry_price\":" + WFJsonPriceValue(symbol,entry_price);
      payload += ",\"current_price\":" + WFJsonPriceValue(symbol,current_price);
      payload += ",\"volume\":" + WFJsonDoubleValue(volume,2);
      payload += ",\"profit\":" + WFJsonDoubleValue(profit,2);
      payload += ",\"swap\":" + WFJsonDoubleValue(swap,2);
      payload += "}";

      row_count++;
     }

   payload += "]";
   return payload;
  }

string WFBuildClosedTradeHistoryJson(const WFRuntimeState &state,const datetime server_now,int &row_count)
  {
   row_count = 0;

   datetime history_from = state.ChallengeStartTime;
   if(history_from <= 0)
      history_from = server_now - (WF_SYNC_HISTORY_LOOKBACK_DAYS * 86400);

   if(server_now <= 0 || history_from <= 0)
      return "[]";

   if(!HistorySelect(history_from,server_now))
     {
      PrintFormat("Wolforix Sync: HistorySelect failed while building trade history (error %d)",GetLastError());
      return "[]";
     }

   WFClosedTradeRow rows[];
   int rows_total = 0;
   int deals_total = (int)HistoryDealsTotal();

   for(int i = deals_total - 1; i >= 0; --i)
     {
      ulong deal_ticket = HistoryDealGetTicket(i);
      if(deal_ticket == 0)
         continue;

      ENUM_DEAL_TYPE deal_type = (ENUM_DEAL_TYPE)HistoryDealGetInteger(deal_ticket,DEAL_TYPE);
      if(!WFSyncIsTradeDealType(deal_type))
         continue;

      ENUM_DEAL_ENTRY deal_entry = (ENUM_DEAL_ENTRY)HistoryDealGetInteger(deal_ticket,DEAL_ENTRY);
      if(!WFSyncIsTradeEntryType(deal_entry))
         continue;

      long position_id = (long)HistoryDealGetInteger(deal_ticket,DEAL_POSITION_ID);
      if(position_id <= 0)
         position_id = (long)deal_ticket;

      int row_index = WFFindClosedTradeRowByPosition(rows,rows_total,position_id);
      if(row_index < 0)
        {
         ArrayResize(rows,rows_total + 1);
         row_index = rows_total;
         rows_total++;
         WFInitClosedTradeRow(rows[row_index],position_id);
        }

      string symbol = HistoryDealGetString(deal_ticket,DEAL_SYMBOL);
      datetime deal_time = (datetime)HistoryDealGetInteger(deal_ticket,DEAL_TIME);
      double deal_price = HistoryDealGetDouble(deal_ticket,DEAL_PRICE);
      double deal_volume = MathAbs(HistoryDealGetDouble(deal_ticket,DEAL_VOLUME));
      string deal_side = WFSideFromDealType(deal_type);

      if(StringLen(rows[row_index].Symbol) == 0)
         rows[row_index].Symbol = symbol;

      if(WFDealEntryHasOpen(deal_entry))
        {
         rows[row_index].HasEntry = true;

         if(rows[row_index].OpenTime <= 0 || deal_time < rows[row_index].OpenTime)
           {
            rows[row_index].OpenTime = deal_time;
            rows[row_index].EntryPrice = deal_price;
            rows[row_index].EntryDealTicket = deal_ticket;
           }

         rows[row_index].OpenVolume += deal_volume;

         if(StringLen(deal_side) > 0)
            rows[row_index].Side = deal_side;
        }

      if(WFDealEntryHasClose(deal_entry))
        {
         rows[row_index].HasExit = true;

         if(rows[row_index].CloseTime <= 0 || deal_time > rows[row_index].CloseTime)
           {
            rows[row_index].CloseTime = deal_time;
            rows[row_index].ExitPrice = deal_price;
            rows[row_index].ExitDealTicket = deal_ticket;
           }

         rows[row_index].ClosedVolume += deal_volume;
         rows[row_index].Profit += HistoryDealGetDouble(deal_ticket,DEAL_PROFIT);
         rows[row_index].Commission += HistoryDealGetDouble(deal_ticket,DEAL_COMMISSION);
         rows[row_index].Swap += HistoryDealGetDouble(deal_ticket,DEAL_SWAP);

         if(StringLen(rows[row_index].Side) == 0 && StringLen(deal_side) > 0)
            rows[row_index].Side = WFOppositeSide(deal_side);
        }
     }

   string payload = "[";
   bool first = true;

   for(int i = 0; i < rows_total; ++i)
     {
      if(row_count >= WF_SYNC_MAX_CLOSED_TRADES)
         break;

      if(!rows[i].HasExit)
         continue;

      if(WFPositionIdentifierOpen(rows[i].PositionId))
         continue;

      if(StringLen(rows[i].Symbol) == 0)
         continue;

      double volume = rows[i].ClosedVolume;
      if(volume <= 0.0)
         volume = rows[i].OpenVolume;

      if(!first)
         payload += ",";
      first = false;

      ulong row_deal_id = rows[i].ExitDealTicket;
      if(row_deal_id == 0)
         row_deal_id = rows[i].EntryDealTicket;
      string entry_price_value = rows[i].HasEntry ? WFJsonPriceValue(rows[i].Symbol,rows[i].EntryPrice) : "null";
      string exit_price_value = rows[i].HasExit ? WFJsonPriceValue(rows[i].Symbol,rows[i].ExitPrice) : "null";

      payload += "{";
      payload += "\"deal_id\":" + StringFormat("%I64u",row_deal_id);
      payload += ",\"position_id\":" + StringFormat("%I64d",rows[i].PositionId);
      payload += ",\"symbol\":\"" + WFJsonEscape(rows[i].Symbol) + "\"";
      payload += ",\"trade_side\":\"" + WFJsonEscape(rows[i].Side) + "\"";
      payload += ",\"open_timestamp\":" + WFJsonDateTimeValue(rows[i].OpenTime);
      payload += ",\"execution_timestamp\":" + WFJsonDateTimeValue(rows[i].CloseTime);
      payload += ",\"entry_price\":" + entry_price_value;
      payload += ",\"exit_price\":" + exit_price_value;
      payload += ",\"volume\":" + WFJsonDoubleValue(volume,2);
      payload += ",\"profit\":" + WFJsonDoubleValue(rows[i].Profit,2);
      payload += ",\"commission\":" + WFJsonDoubleValue(rows[i].Commission,2);
      payload += ",\"swap\":" + WFJsonDoubleValue(rows[i].Swap,2);
      payload += "}";

      row_count++;
     }

   payload += "]";
   return payload;
  }

int WFBuildUtf8PostData(const string text,char &buffer[])
  {
   uchar temp[];
   int copied = StringToCharArray(text,temp,0,-1,CP_UTF8);
   if(copied <= 0)
     {
      ArrayResize(buffer,0);
      return 0;
     }

   int size = copied;
   if(size > 0 && temp[size - 1] == 0)
      size--;

   ArrayResize(buffer,size);
   for(int i = 0; i < size; ++i)
      buffer[i] = (char)temp[i];

   return size;
  }

string WFResponseBodyToString(const char &buffer[])
  {
   int size = ArraySize(buffer);
   if(size <= 0)
      return "";

   uchar temp[];
   ArrayResize(temp,size);
   for(int i = 0; i < size; ++i)
      temp[i] = (uchar)buffer[i];

   return CharArrayToString(temp,0,size,CP_UTF8);
  }

string WFBuildMetricsUrl(const string api_base_url,const string account_reference)
  {
   string base_url = WFTrimTrailingSlash(api_base_url);
   string reference = WFTrimInputValue(account_reference);
   return base_url + "/api/integrations/mt5/accounts/" + reference + "/metrics";
  }

string WFBuildMetricsPayload(const WFRuleSet &rules,
                             const WFRuntimeState &state,
                             const WFMetrics &metrics,
                             const datetime server_now,
                             const string trigger)
  {
   int open_positions_count = 0;
   int closed_trades_count = 0;
   string open_positions_json = WFBuildOpenPositionsJson(open_positions_count);
   string trade_history_json = WFBuildClosedTradeHistoryJson(state,server_now,closed_trades_count);
   bool has_activity = (StringFind(trigger,"trade") >= 0 ||
                        StringFind(trigger,"deal") >= 0 ||
                        StringFind(trigger,"order") >= 0 ||
                        StringFind(trigger,"position") >= 0 ||
                        open_positions_count > 0 ||
                        closed_trades_count > 0);
   int activity_count = open_positions_count + closed_trades_count;
   if(has_activity && activity_count <= 0)
      activity_count = 1;

   string payload = "{";
   payload += "\"balance\":" + DoubleToString(metrics.Balance,2);
   payload += ",\"equity\":" + DoubleToString(metrics.Equity,2);
   payload += ",\"open_profit\":" + DoubleToString(metrics.FloatingPnL,2);
   payload += ",\"highest_equity_today\":" + DoubleToString(state.DayHighestEquity,2);
   payload += ",\"daily_loss_used\":" + DoubleToString(metrics.DailyLossAmount,2);
   payload += ",\"daily_loss_limit\":" + DoubleToString(metrics.DailyLossLimitAmount,2);
   payload += ",\"max_drawdown_used\":" + DoubleToString(metrics.MaxDrawdownAmount,2);
   payload += ",\"max_drawdown_limit\":" + DoubleToString(metrics.MaxDrawdownLimitAmount,2);
   payload += ",\"trading_days\":" + IntegerToString(metrics.TradingDaysCount);
   payload += ",\"phase\":\"" + WFJsonEscape(rules.Code) + "\"";
   payload += ",\"phase_label\":\"" + WFJsonEscape(rules.Label) + "\"";
   payload += ",\"challenge_id\":\"" + WFJsonEscape(state.ChallengeId) + "\"";
   payload += ",\"challenge_status\":\"" + WFJsonEscape(WFEngineStatusText(state.Status)) + "\"";
   payload += ",\"platform_status\":\"connected\"";
   long account_login = (long)AccountInfoInteger(ACCOUNT_LOGIN);
   payload += ",\"platform_account_id\":\"" + StringFormat("%I64d",account_login) + "\"";
   payload += ",\"platform_login\":\"" + StringFormat("%I64d",account_login) + "\"";
   payload += ",\"positions_count\":" + IntegerToString(open_positions_count);
   payload += ",\"closed_positions_count\":" + IntegerToString(closed_trades_count);
   payload += ",\"trade_count\":" + IntegerToString(closed_trades_count);
   payload += ",\"has_activity\":" + WFJsonBool(has_activity);
   payload += ",\"activity_count\":" + IntegerToString(activity_count);
   payload += ",\"sync_trigger\":\"" + WFJsonEscape(trigger) + "\"";
   payload += ",\"server_time\":\"" + WFJsonEscape(WFSerializeDateTime(server_now)) + "\"";
   payload += ",\"open_positions\":" + open_positions_json;
   payload += ",\"trade_history\":" + trade_history_json;
   payload += "}";
   return payload;
  }

bool WFSendMetricsToBackend(const string api_base_url,
                            const string api_token,
                            const string account_reference,
                            const WFRuleSet &rules,
                            const WFRuntimeState &state,
                            const WFMetrics &metrics,
                            const datetime server_now,
                            const string trigger,
                            WFSyncState &sync_state)
  {
   sync_state.Enabled = true;
   sync_state.LastAttemptTime = server_now;
   sync_state.LastHttpCode = 0;
   sync_state.LastFailureReason = "";

   string base_url = WFTrimTrailingSlash(api_base_url);
   string token = WFTrimInputValue(api_token);
   string reference = WFTrimInputValue(account_reference);

   if(StringLen(base_url) == 0)
     {
      sync_state.LastResult = "Skipped: ApiBaseUrl empty";
      PrintFormat("Wolforix Sync: failure reason %s",sync_state.LastResult);
      return false;
     }

   if(StringLen(reference) == 0)
     {
      sync_state.LastResult = "Skipped: AccountReference empty";
      PrintFormat("Wolforix Sync: failure reason %s",sync_state.LastResult);
      return false;
     }

   string url = WFBuildMetricsUrl(base_url,reference);
   string payload = WFBuildMetricsPayload(rules,state,metrics,server_now,trigger);
   string headers = "Authorization: Bearer " + token + "\r\n";
   headers += "Content-Type: application/json\r\n";
   headers += "Accept: application/json\r\n";

   char request_body[];
   char response_body[];
   string response_headers = "";
   WFBuildUtf8PostData(payload,request_body);

   sync_state.LastRequestUrl = url;

   PrintFormat("Wolforix Sync: started (%s)",trigger);
   PrintFormat("Wolforix Sync: request URL %s",url);

   ResetLastError();
   int http_code = WebRequest("POST",url,headers,WF_SYNC_TIMEOUT_MS,request_body,response_body,response_headers);
   int request_error = GetLastError();
   string response_text = WFResponseBodyToString(response_body);

   sync_state.LastHttpCode = http_code;
   sync_state.LastResponseBody = response_text;

   PrintFormat("Wolforix Sync: response code %d",http_code);
   PrintFormat("Wolforix Sync: response body %s",response_text);

   if(http_code == -1)
     {
      sync_state.LastResult = "FAILED";
      sync_state.LastFailureReason = "WebRequest error " + IntegerToString(request_error);
      PrintFormat("Wolforix Sync: failure reason %s",sync_state.LastFailureReason);
      return false;
     }

   if(http_code >= 200 && http_code < 300)
     {
      sync_state.LastSuccessTime = server_now;
      sync_state.LastResult = "SUCCESS";
      PrintFormat("Wolforix Sync: success");
      return true;
     }

   sync_state.LastResult = "FAILED";
   sync_state.LastFailureReason = "HTTP " + IntegerToString(http_code);
   PrintFormat("Wolforix Sync: failure reason %s",sync_state.LastFailureReason);
   return false;
  }

#endif
