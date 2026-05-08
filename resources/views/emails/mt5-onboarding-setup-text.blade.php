Connect Your MT5 Account to Wolforix Dashboard

Hello,

Your MT5 account is active. To start synchronizing Wolforix dashboard metrics, install and attach the Wolforix MT5 Connector EA inside MetaTrader 5.

Your MT5 account is active, but dashboard metrics only update after the connector is installed, attached to a chart, and successfully synced. The connector sends Wolforix the dashboard metrics needed for real-time P/L, trading days, account statistics, and profit updates.

Required setup:

1. Log in to your Wolforix dashboard:
   {{ $dashboardUrl }}
2. Open the MT5 setup page:
   {{ $setupUrl }}
3. Watch the MT5 setup tutorial video:
   {{ $setupVideoUrl }}
4. Download the preconfigured connector package from the setup page.
5. Extract the ZIP file on your computer.
6. Copy WolforixRuleEngineEA.mq5 into MQL5/Experts.
7. Copy the Include files from the package into MQL5/Include.
8. Open MetaTrader 5.
9. Enable Algo Trading.
10. Go to Tools > Options > Expert Advisors.
11. Allow WebRequest for:
    https://www.wolforix.com
    https://wolforix.com
12. Attach the EA to an active chart.
13. Wait for the dashboard status to show Connected.

Important:
Until the EA connector is installed, attached to a chart, and successfully synced, dashboard metrics such as real-time P/L, trading days, account statistics, and profit updates may not appear.

Links:
Open Wolforix Dashboard: {{ $dashboardUrl }}
Open MT5 Setup Page: {{ $setupUrl }}
Watch Setup Video: {{ $setupVideoUrl }}

Support:
{{ $supportEmail }}

Best regards,
Wolforix Team
