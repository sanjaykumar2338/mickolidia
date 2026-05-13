Action Required: Install the Updated Wolforix MT5 Connector

Hello,

We recently released updated Wolforix MT5 Connector improvements ({{ $connectorReleaseLabel }}). Please log in to your Wolforix dashboard, download the latest EA connector files, and reconnect MetaTrader 5 so live synchronization, rule monitoring, and dashboard metrics work correctly.

Some earlier MT5 sync/connectivity issues may have affected proper rule tracking and monitoring. To make sure your Wolforix dashboard reads the correct live data, please replace the previous connector files with the latest package from your dashboard, attach the updated EA to MT5, and allow it to complete a fresh sync.

Thank you sincerely for your patience and support while we stabilized the connector. As a goodwill step, Wolforix will provide you with a completely free funded account opportunity so you can continue testing your trading skills with us.

Required setup:

1. Log in to your Wolforix dashboard:
   {{ $dashboardUrl }}
2. Open the MT5 setup page:
   {{ $setupUrl }}
3. Watch the MT5 setup tutorial video:
   {{ $setupVideoUrl }}
4. Download the latest preconfigured connector package from the setup page.
5. Extract the ZIP file on your computer.
6. Replace any older Wolforix connector files with the updated package files.
7. Copy WolforixRuleEngineEA.mq5 into MQL5/Experts.
8. Copy the Include files from the package into MQL5/Include.
9. Open MetaTrader 5.
10. Enable Algo Trading.
11. Go to Tools > Options > Expert Advisors.
12. Allow WebRequest for:
    https://www.wolforix.com
    https://wolforix.com
13. Attach the EA to an active chart.
14. Wait for the dashboard status to show Connected.

Important:
Please use the updated connector package from your dashboard rather than an older ZIP. Until the updated EA connector is installed, attached to a chart, and successfully synced, live P/L, rule monitoring, trading days, account statistics, and profit updates may not appear correctly.

Links:
Open Wolforix Dashboard: {{ $dashboardUrl }}
Open MT5 Setup Page: {{ $setupUrl }}
Latest Connector ZIP: {{ $connectorDownloadUrl }}
Connector Release: {{ $connectorReleaseLabel }}
Watch Setup Video: {{ $setupVideoUrl }}

Support:
{{ $supportEmail }}

Best regards,
Wolforix Team
