#ifndef __WOLFORIX_TYPES_MQH__
#define __WOLFORIX_TYPES_MQH__

enum WFChallengePreset
  {
   WF_CHALLENGE_ONE_STEP = 0,
   WF_CHALLENGE_TWO_STEP_PHASE_1 = 1,
   WF_CHALLENGE_TWO_STEP_PHASE_2 = 2
  };

enum WFDailyLossMode
  {
   WF_DAILY_LOSS_FROM_DAY_START_BALANCE = 0,
   WF_DAILY_LOSS_FROM_DAY_HIGH_EQUITY = 1
  };

enum WFEngineStatus
  {
   WF_STATUS_ACTIVE = 0,
   WF_STATUS_PASSED = 1,
   WF_STATUS_FAILED = 2
  };

struct WFRuleSet
  {
   string            Code;
   string            Label;
   double            ProfitTargetPct;
   double            DailyLossPct;
   double            MaxDrawdownPct;
   int               MinTradingDays;
  };

struct WFRuntimeState
  {
   int               Version;
   long              AccountLogin;
   string            ChallengeId;
   string            PresetCode;
   int               Status;
   bool              TradingBlocked;
   datetime          ChallengeStartTime;
   datetime          PassedAt;
   datetime          FailedAt;
   string            BreachReason;
   double            InitialBalance;
   double            InitialEquity;
   int               CurrentDayKey;
   datetime          CurrentDayOpenTime;
   double            DayStartBalance;
   double            DayStartEquity;
   double            DayHighestEquity;
   double            PeakBalance;
   double            PeakEquity;
   int               LastTradingDayCount;
   datetime          LastEvaluationTime;
  };

struct WFMetrics
  {
   double            Balance;
   double            Equity;
   double            FloatingPnL;
   double            ProfitAmount;
   double            ProfitTargetAmount;
   double            ProfitRemaining;
   bool              ProfitTargetReached;
   double            DailyLossReference;
   double            DailyLossAmount;
   double            DailyLossLimitAmount;
   double            DailyLossRemaining;
   bool              DailyLossBreached;
   double            MaxDrawdownAmount;
   double            MaxDrawdownLimitAmount;
   double            MaxDrawdownRemaining;
   double            DrawdownFloor;
   bool              MaxDrawdownBreached;
   int               TradingDaysCount;
   bool              MinTradingDaysReached;
   bool              Passed;
  };

struct WFSyncState
  {
   bool              Enabled;
   datetime          LastAttemptTime;
   datetime          LastSuccessTime;
   int               LastHttpCode;
   string            LastResult;
   string            LastFailureReason;
   string            LastRequestUrl;
   string            LastResponseBody;
  };

void WFPopulateRuleSet(const WFChallengePreset preset,WFRuleSet &rules)
  {
   switch(preset)
     {
      case WF_CHALLENGE_ONE_STEP:
         rules.Code            = "one_step";
         rules.Label           = "1-Step Challenge";
         rules.ProfitTargetPct = 10.0;
         rules.DailyLossPct    = 4.0;
         rules.MaxDrawdownPct  = 8.0;
         rules.MinTradingDays  = 3;
         return;

      case WF_CHALLENGE_TWO_STEP_PHASE_1:
         rules.Code            = "two_step_phase_1";
         rules.Label           = "2-Step Phase 1";
         rules.ProfitTargetPct = 10.0;
         rules.DailyLossPct    = 5.0;
         rules.MaxDrawdownPct  = 10.0;
         rules.MinTradingDays  = 3;
         return;

      case WF_CHALLENGE_TWO_STEP_PHASE_2:
      default:
         rules.Code            = "two_step_phase_2";
         rules.Label           = "2-Step Phase 2";
         rules.ProfitTargetPct = 5.0;
         rules.DailyLossPct    = 5.0;
         rules.MaxDrawdownPct  = 10.0;
         rules.MinTradingDays  = 3;
         return;
     }
  }

string WFEngineStatusText(const int status)
  {
   switch(status)
     {
      case WF_STATUS_ACTIVE:
         return "ACTIVE";
      case WF_STATUS_PASSED:
         return "PASSED";
      case WF_STATUS_FAILED:
         return "FAILED";
      default:
         return "UNKNOWN";
     }
  }

string WFDailyLossModeText(const WFDailyLossMode mode)
  {
   switch(mode)
     {
      case WF_DAILY_LOSS_FROM_DAY_START_BALANCE:
         return "DAY_START_BALANCE";
      case WF_DAILY_LOSS_FROM_DAY_HIGH_EQUITY:
         return "DAY_HIGH_EQUITY";
      default:
         return "UNKNOWN";
     }
  }

string WFYesNo(const bool value)
  {
   if(value)
      return "YES";
   return "NO";
  }

string WFHttpCodeText(const int value)
  {
   if(value == 0)
      return "-";
   return IntegerToString(value);
  }

string WFSanitizeKey(string value)
  {
   StringTrimLeft(value);
   StringTrimRight(value);

   if(StringLen(value) == 0)
      return "default";

   StringReplace(value," ","_");
   StringReplace(value,"\\","_");
   StringReplace(value,"/","_");
   StringReplace(value,":","_");
   StringReplace(value,";","_");
   StringReplace(value,"|","_");
   StringReplace(value,"*","_");
   StringReplace(value,"?","_");
   StringReplace(value,"\"","_");
   StringReplace(value,"<","_");
   StringReplace(value,">","_");

   return value;
  }

string WFFmtMoney(const double value)
  {
   return DoubleToString(value,2);
  }

string WFFmtPct(const double value)
  {
   return DoubleToString(value,2) + "%";
  }

string WFFmtDateTime(const datetime value)
  {
   if(value <= 0)
      return "-";
   return TimeToString(value,TIME_DATE | TIME_MINUTES | TIME_SECONDS);
  }

string WFSerializeDateTime(const datetime value)
  {
   if(value <= 0)
      return "0";
   return TimeToString(value,TIME_DATE | TIME_MINUTES | TIME_SECONDS);
  }

datetime WFParseDateTime(const string value)
  {
   if(value == "0" || value == "-" || StringLen(value) == 0)
      return 0;
   return StringToTime(value);
  }

void WFResetSyncState(WFSyncState &state)
  {
   state.Enabled           = false;
   state.LastAttemptTime   = 0;
   state.LastSuccessTime   = 0;
   state.LastHttpCode      = 0;
   state.LastResult        = "Disabled";
   state.LastFailureReason = "";
   state.LastRequestUrl    = "";
   state.LastResponseBody  = "";
  }

#endif
