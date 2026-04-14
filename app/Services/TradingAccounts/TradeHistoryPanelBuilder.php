<?php

namespace App\Services\TradingAccounts;

use App\Models\TradingAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class TradeHistoryPanelBuilder
{
    private const RECENT_SNAPSHOT_LIMIT = 50;

    private const OPEN_POSITION_PATHS = [
        'open_positions',
        'openPositions',
        'open_trades',
        'openTrades',
        'active_trades',
        'activeTrades',
        'open.positions',
        'positions.open',
        'positions',
        'positions_open',
        'positionsOpen',
        'current_positions',
        'currentPositions',
        'trades.open',
        'trades.active',
        'raw.open_positions',
        'raw.openPositions',
        'raw.open_trades',
        'raw.openTrades',
        'raw.active_trades',
        'raw.activeTrades',
        'raw.open.positions',
        'raw.positions.open',
        'raw.positions',
        'raw.positions_open',
        'raw.positionsOpen',
        'raw.current_positions',
        'raw.currentPositions',
        'raw.trades.open',
        'raw.trades.active',
        'mt5.open_positions',
        'mt5.openPositions',
        'mt5.open_trades',
        'mt5.openTrades',
        'mt5.active_trades',
        'mt5.activeTrades',
        'mt5.positions',
        'mt5.positions_open',
        'mt5.positionsOpen',
        'mt5.trades.open',
        'mt5.trades.active',
    ];

    private const CLOSED_TRADE_PATHS = [
        'trade_history',
        'tradeHistory',
        'closed_trades',
        'closedTrades',
        'closed_positions',
        'closedPositions',
        'closed_orders',
        'closedOrders',
        'closed_deals',
        'closedDeals',
        'positions.closed',
        'history',
        'history_orders',
        'historyOrders',
        'history_deals',
        'historyDeals',
        'deal_history',
        'dealHistory',
        'deals',
        'orders',
        'orders.closed',
        'deals.closed',
        'trades.closed',
        'trades.history',
        'raw.trade_history',
        'raw.tradeHistory',
        'raw.closed_trades',
        'raw.closedTrades',
        'raw.closed_positions',
        'raw.closedPositions',
        'raw.closed_orders',
        'raw.closedOrders',
        'raw.closed_deals',
        'raw.closedDeals',
        'raw.positions.closed',
        'raw.history',
        'raw.history_orders',
        'raw.historyOrders',
        'raw.history_deals',
        'raw.historyDeals',
        'raw.deal_history',
        'raw.dealHistory',
        'raw.deals',
        'raw.orders',
        'raw.orders.closed',
        'raw.deals.closed',
        'raw.trades.closed',
        'raw.trades.history',
        'mt5.trade_history',
        'mt5.tradeHistory',
        'mt5.closed_trades',
        'mt5.closedTrades',
        'mt5.closed_orders',
        'mt5.closedOrders',
        'mt5.closed_deals',
        'mt5.closedDeals',
        'mt5.history',
        'mt5.history_orders',
        'mt5.historyOrders',
        'mt5.history_deals',
        'mt5.historyDeals',
        'mt5.deals',
        'mt5.orders',
        'mt5.orders.closed',
        'mt5.trades.closed',
        'mt5.trades.history',
    ];

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function build(?TradingAccount $account, array $options = []): array
    {
        $filters = [
            ['key' => 'both', 'label' => __('Both')],
            ['key' => 'open', 'label' => __('Open')],
            ['key' => 'closed', 'label' => __('Closed')],
        ];

        $emptyState = [
            'is_available' => false,
            'rows' => [],
            'filters' => $filters,
            'summary' => [
                'open' => 0,
                'closed' => 0,
                'both' => 0,
            ],
            'visible_columns' => [
                'entry_price' => false,
                'exit_price' => false,
                'duration' => false,
                'commission' => false,
                'swap' => false,
                'net_result' => false,
            ],
            'message' => (string) ($options['empty_message'] ?? __('Trade visualization activates once detailed MT5 trade rows are synced for this account. Daily activity counts may already appear in the summary above.')),
            'source' => __('Snapshot payload'),
        ];

        if (! $account instanceof TradingAccount) {
            return $emptyState;
        }

        $snapshotTradePayload = $this->latestSnapshotTradePayload($account);
        $openRows = collect($snapshotTradePayload['open_positions'] ?? [])
            ->map(fn (array $row): array => $this->buildTradeRow($row, true));
        $closedRows = collect($snapshotTradePayload['trade_history'] ?? [])
            ->map(fn (array $row): array => $this->buildTradeRow($row, false));

        $rows = $openRows
            ->concat($closedRows)
            ->sortByDesc('sort_timestamp')
            ->values()
            ->map(fn (array $row): array => Arr::except($row, ['sort_timestamp']))
            ->all();

        if ($rows === []) {
            $emptyState['source'] = $this->sourceLabel((string) ($account->sync_source ?: 'snapshot_payload'));
            $emptyState['message'] = (string) ($snapshotTradePayload['empty_message'] ?? $emptyState['message']);

            return $emptyState;
        }

        $rowCollection = collect($rows);

        return [
            'is_available' => true,
            'rows' => $rows,
            'filters' => $filters,
            'summary' => [
                'open' => $openRows->count(),
                'closed' => $closedRows->count(),
                'both' => count($rows),
            ],
            'visible_columns' => [
                'entry_price' => $rowCollection->contains(fn (array $row): bool => $row['entry_price'] !== null),
                'exit_price' => $rowCollection->contains(fn (array $row): bool => $row['exit_price'] !== null),
                'duration' => $rowCollection->contains(fn (array $row): bool => $row['duration'] !== null),
                'commission' => $rowCollection->contains(fn (array $row): bool => $row['commission'] !== null),
                'swap' => $rowCollection->contains(fn (array $row): bool => $row['swap'] !== null),
                'net_result' => $rowCollection->contains(fn (array $row): bool => $row['net_result'] !== null),
            ],
            'message' => (string) ($snapshotTradePayload['available_message'] ?? $options['available_message'] ?? __('The latest synced snapshot powers this table. Open and closed rows are shown only when the platform payload includes them.')),
            'source' => $this->sourceLabel((string) ($account->sync_source ?: 'snapshot_payload')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTradeRow(array $row, bool $isOpen): array
    {
        $openedAt = $this->tradeOpenTimestamp($row);
        $closedAt = $isOpen ? null : $this->tradeCloseTimestamp($row);
        $commission = $this->tradeCommissionValue($row);
        $swap = $this->tradeSwapValue($row);
        $pnl = $this->tradePnlValue($row);
        $netResult = $this->tradeNetResultValue($row, $pnl, $commission, $swap);
        $resultAmount = $netResult ?? $pnl ?? 0.0;
        $status = $this->tradeStatus($isOpen, $resultAmount);
        $sideLabel = $this->tradeSideLabel($this->tradeSideValue($row));

        return [
            'filter' => $isOpen ? 'open' : 'closed',
            'id' => (string) ($this->tradeIdentifier($row, $isOpen) ?? __('—')),
            'symbol' => $this->tradeSymbolLabel($row),
            'side' => $sideLabel,
            'side_tone' => $this->tradeSideTone($sideLabel),
            'status' => $status['label'],
            'status_tone' => $status['tone'],
            'open_date' => $this->formatTradeDate($openedAt),
            'close_date' => $isOpen ? __('Ongoing') : $this->formatTradeDate($closedAt),
            'entry_price' => $this->formatPrice($this->tradeEntryPriceValue($row)),
            'exit_price' => $isOpen ? null : $this->formatPrice($this->tradeExitPriceValue($row)),
            'volume' => $this->formatNumber($this->tradeVolumeValue($row), 0, 2) ?? __('—'),
            'profit' => $this->formatMoney($pnl ?? 0),
            'profit_tone' => $this->metricTone($pnl ?? 0),
            'commission' => $commission !== null ? $this->formatMoney($commission) : null,
            'swap' => $swap !== null ? $this->formatMoney($swap) : null,
            'net_result' => $netResult !== null ? $this->formatMoney($netResult) : null,
            'net_result_tone' => $this->metricTone($netResult ?? 0),
            'duration' => $this->formatTradeDuration($openedAt, $closedAt, $isOpen),
            'sort_timestamp' => $closedAt?->getTimestamp() ?? $openedAt?->getTimestamp() ?? 0,
        ];
    }

    /**
     * @return array{label:string,tone:string}
     */
    private function tradeStatus(bool $isOpen, float $resultAmount): array
    {
        if ($isOpen) {
            return [
                'label' => __('Open'),
                'tone' => 'amber',
            ];
        }

        return match (true) {
            $resultAmount > 0.009 => ['label' => __('Win'), 'tone' => 'emerald'],
            $resultAmount < -0.009 => ['label' => __('Loss'), 'tone' => 'rose'],
            default => ['label' => __('Closed'), 'tone' => 'slate'],
        };
    }

    private function tradeSideTone(string $side): string
    {
        return match (strtolower($side)) {
            'buy' => 'sky',
            'sell' => 'rose',
            default => 'slate',
        };
    }

    private function tradeIdentifier(array $row, bool $isOpen): mixed
    {
        return $this->firstFilledValue($row, $isOpen
            ? ['position_id', 'positionId', 'position_ticket', 'positionTicket', 'ticket', 'ticket_number', 'ticketNumber', 'Ticket', 'order_id', 'orderId', 'order', 'Order', 'id']
            : ['deal_id', 'dealId', 'deal_ticket', 'dealTicket', 'position_id', 'positionId', 'ticket', 'ticket_number', 'ticketNumber', 'Ticket', 'order_id', 'orderId', 'order', 'Order', 'id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function latestSnapshotTradePayload(TradingAccount $account): array
    {
        $snapshots = $account->balanceSnapshots()
            ->orderByDesc('snapshot_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_SNAPSHOT_LIMIT)
            ->get(['id', 'snapshot_at', 'payload']);

        $latestSnapshot = $snapshots->first();
        $payload = is_array($latestSnapshot?->payload) ? $latestSnapshot->payload : [];
        $latestOpenRows = $this->tradeRowsFromPayload($payload, self::OPEN_POSITION_PATHS);
        $latestClosedRows = $this->tradeRowsFromPayload($payload, self::CLOSED_TRADE_PATHS, true);
        $openRows = $latestOpenRows;
        $closedRows = $latestClosedRows;
        $usedOpenFallback = false;
        $usedClosedFallback = false;
        $latestOpenExplicitlyEmpty = $this->payloadDeclaresNoOpenRows($payload, $latestOpenRows);

        foreach ($snapshots->slice(1) as $snapshot) {
            $snapshotPayload = is_array($snapshot->payload) ? $snapshot->payload : [];

            if ($openRows === [] && ! $latestOpenExplicitlyEmpty) {
                $snapshotOpenRows = $this->tradeRowsFromPayload($snapshotPayload, self::OPEN_POSITION_PATHS);

                if ($snapshotOpenRows !== []) {
                    $openRows = $snapshotOpenRows;
                    $usedOpenFallback = true;
                }
            }

            $snapshotClosedRows = $this->tradeRowsFromPayload($snapshotPayload, self::CLOSED_TRADE_PATHS, true);

            if ($snapshotClosedRows !== []) {
                if ($latestClosedRows === [] && $closedRows === []) {
                    $usedClosedFallback = true;
                }

                $closedRows = $this->mergeTradeRows($closedRows, $snapshotClosedRows);
            }
        }

        return [
            'snapshot_at' => $latestSnapshot?->snapshot_at,
            'open_positions' => $openRows,
            'trade_history' => $closedRows,
            'empty_message' => $this->missingTradeRowsMessage($payload),
            'available_message' => $usedOpenFallback || $usedClosedFallback
                ? __('Showing the latest persisted detailed trade rows. A newer MT5 sync updated account metrics without row-level trade data.')
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $paths
     * @return list<array<string, mixed>>
     */
    private function tradeRowsFromPayload(array $payload, array $paths, bool $allowAggregateFallback = false): array
    {
        $rows = collect($paths)
            ->flatMap(fn (string $path): array => $this->coerceTradeRows(Arr::get($payload, $path)))
            ->filter(fn ($row): bool => is_array($row))
            ->values();

        if ($rows->isEmpty() && $allowAggregateFallback) {
            $singleSymbol = $this->firstFilledValue($payload, [
                'symbol',
                'Symbol',
                'trade_symbol',
                'tradeSymbol',
                'last_symbol',
                'lastSymbol',
                'instrument',
                'Instrument',
                'raw.symbol',
                'raw.Symbol',
                'raw.trade_symbol',
                'raw.last_symbol',
            ]);

            $singleCount = $this->tradeCountValue($payload);

            if ($singleSymbol !== null && $singleCount > 0) {
                $rows = collect([[
                    'symbol' => $singleSymbol,
                    'trade_count' => $singleCount,
                    'volume' => $this->tradeVolumeValue($payload),
                    'net_profit' => $this->tradePnlValue($payload),
                ]]);
            }
        }

        return $this->uniqueTradeRows($rows->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function coerceTradeRows(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return collect($value)
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all();
        }

        foreach ([
            'data',
            'items',
            'rows',
            'records',
            'positions',
            'open_positions',
            'openPositions',
            'current_positions',
            'currentPositions',
            'openTrades',
            'deals',
            'orders',
            'history',
            'trade_history',
            'tradeHistory',
            'closed_trades',
            'closedTrades',
            'closed_orders',
            'closedOrders',
            'closed_deals',
            'closedDeals',
            'trades',
            'payload',
            'result',
            'response',
        ] as $key) {
            if (isset($value[$key]) && is_array($value[$key])) {
                return $this->coerceTradeRows($value[$key]);
            }
        }

        $keyedRows = collect($value)
            ->filter(fn ($row): bool => is_array($row) && $this->tradeSymbolLabel($row) !== __('—'))
            ->values();

        if ($keyedRows->isNotEmpty()) {
            return $keyedRows->all();
        }

        return $this->tradeSymbolLabel($value) !== __('—') ? [$value] : [];
    }

    private function tradeIdentityKey(array $row): ?string
    {
        $id = $this->firstFilledValue($row, [
            'deal_id',
            'dealId',
            'deal_ticket',
            'dealTicket',
            'position_id',
            'positionId',
            'position_ticket',
            'positionTicket',
            'ticket',
            'ticket_number',
            'ticketNumber',
            'Ticket',
            'order_id',
            'orderId',
            'order',
            'Order',
            'id',
            'raw.deal_id',
            'raw.dealId',
            'raw.position_id',
            'raw.positionId',
            'raw.ticket',
            'raw.Ticket',
        ]);

        if ($id !== null) {
            return 'id|'.(string) $id;
        }

        $timestamp = $this->firstFilledValue($row, [
            'open_timestamp',
            'open_time',
            'openTime',
            'time',
            'Time',
            'close_timestamp',
            'close_time',
            'closeTime',
            'execution_timestamp',
            'execution_time',
            'executionTime',
            'TimeClose',
        ]);

        if ($timestamp === null) {
            return null;
        }

        return implode('|', [
            'time',
            $this->tradeSymbolLabel($row),
            (string) $timestamp,
            (string) $this->tradeVolumeValue($row),
            (string) ($this->tradePnlValue($row) ?? 0),
        ]);
    }

    private function tradeSymbolLabel(array $row): string
    {
        $symbol = $this->firstFilledValue($row, [
            'symbol',
            'Symbol',
            'SYMBOL',
            'symbol_name',
            'symbolName',
            'SymbolName',
            'instrument_name',
            'instrumentName',
            'instrument',
            'Instrument',
            'market',
            'Market',
            'ticker',
            'Ticker',
            'raw.symbolName',
            'raw.SymbolName',
            'raw.symbol',
            'raw.Symbol',
            'raw.tradeData.symbolName',
            'raw.tradeData.symbol',
            'raw.tradeData.Symbol',
            'symbol_id',
            'symbolId',
        ]);

        if (blank($symbol)) {
            return __('—');
        }

        if (is_numeric($symbol)) {
            return __('Symbol #:value', ['value' => $symbol]);
        }

        return (string) $symbol;
    }

    private function tradeCountValue(array $row): int
    {
        $count = $this->firstFilledValue($row, [
            'trade_count',
            'tradeCount',
            'activity_count',
            'activityCount',
            'count',
            'Count',
            'deals_count',
            'dealsCount',
            'positions_count',
            'positionsCount',
            'raw.trade_count',
            'raw.count',
        ]);

        return max((int) ($count ?? 1), 1);
    }

    private function tradeVolumeValue(array $row): ?float
    {
        $value = $this->firstFilledValue($row, [
            'total_volume',
            'totalVolume',
            'filled_volume',
            'filledVolume',
            'volume',
            'volume_lots',
            'volumeLots',
            'Volume',
            'lots',
            'Lots',
            'lot',
            'Lot',
            'lot_size',
            'lotSize',
            'raw.total_volume',
            'raw.volume',
            'raw.Volume',
            'raw.tradeData.volume',
        ]);

        return $value !== null ? round((float) $value, 2) : null;
    }

    private function tradePnlValue(array $row): ?float
    {
        $value = $this->firstFilledValue($row, [
            'gross_profit',
            'grossProfit',
            'gross_unrealized_pnl',
            'grossUnrealizedPnl',
            'profit',
            'Profit',
            'pnl',
            'Pnl',
            'PNL',
            'floating_profit',
            'floatingProfit',
            'unrealized_profit',
            'unrealizedProfit',
            'realized_profit',
            'realizedProfit',
            'total_profit',
            'totalProfit',
            'today_profit',
            'todayProfit',
            'net_profit',
            'netProfit',
            'net_unrealized_pnl',
            'netUnrealizedPnl',
            'raw.gross_profit',
            'raw.grossProfit',
            'raw.profit',
            'raw.Profit',
            'raw.pnl',
            'raw.tradeData.profit',
        ]);

        return $value !== null ? round((float) $value, 2) : null;
    }

    private function tradeCommissionValue(array $row): ?float
    {
        $value = $this->firstFilledValue($row, [
            'commission',
            'Commission',
            'fee',
            'Fee',
            'fees',
            'Fees',
            'raw.commission',
            'raw.Commission',
            'raw.fee',
            'raw.fees',
            'raw.closePositionDetail.commission',
        ]);

        return $value !== null ? round((float) $value, 2) : null;
    }

    private function tradeSwapValue(array $row): ?float
    {
        $value = $this->firstFilledValue($row, [
            'swap',
            'Swap',
            'swap_charge',
            'swapCharge',
            'raw.swap',
            'raw.Swap',
            'raw.closePositionDetail.swap',
        ]);

        return $value !== null ? round((float) $value, 2) : null;
    }

    private function tradeNetResultValue(array $row, ?float $pnl, ?float $commission, ?float $swap): ?float
    {
        $explicit = $this->firstFilledValue($row, [
            'net_result',
            'netResult',
            'net_profit',
            'netProfit',
            'net_unrealized_pnl',
            'netUnrealizedPnl',
            'result',
            'raw.net_result',
            'raw.netResult',
            'raw.net_profit',
            'raw.netProfit',
            'raw.net_unrealized_pnl',
            'raw.tradeData.netProfit',
        ]);

        if ($explicit !== null) {
            return round((float) $explicit, 2);
        }

        if ($pnl === null || ($commission === null && $swap === null)) {
            return null;
        }

        return round($pnl + ($commission ?? 0) + ($swap ?? 0), 2);
    }

    private function tradeSideValue(array $row): mixed
    {
        return $this->firstFilledValue($row, [
            'trade_side',
            'tradeSide',
            'side',
            'Side',
            'direction',
            'Direction',
            'cmd',
            'Cmd',
            'action',
            'Action',
            'type',
            'Type',
            'position_type',
            'positionType',
            'order_type',
            'orderType',
            'raw.trade_side',
            'raw.side',
            'raw.type',
            'raw.Type',
            'raw.tradeData.side',
            'raw.tradeData.tradeSide',
            'raw.tradeData.type',
        ]);
    }

    private function tradeSideLabel(mixed $value): string
    {
        $normalized = strtolower((string) $value);
        $numeric = is_numeric($value) ? (int) $value : null;

        if ($numeric !== null) {
            if (in_array($numeric, [0, 2, 4, 6], true)) {
                return __('Buy');
            }

            if (in_array($numeric, [1, 3, 5, 7], true)) {
                return __('Sell');
            }
        }

        return match (true) {
            str_contains($normalized, 'buy'),
            str_contains($normalized, 'long') => __('Buy'),
            str_contains($normalized, 'sell'),
            str_contains($normalized, 'short') => __('Sell'),
            $normalized !== '' => str($normalized)->replace('_', ' ')->title()->toString(),
            default => __('—'),
        };
    }

    private function tradeEntryPriceValue(array $row): ?float
    {
        $value = $this->firstFilledValue($row, [
            'entry_price',
            'entryPrice',
            'open_price',
            'openPrice',
            'price_open',
            'priceOpen',
            'PriceOpen',
            'price',
            'Price',
            'open_rate',
            'openRate',
            'raw.entry_price',
            'raw.entryPrice',
            'raw.open_price',
            'raw.openPrice',
            'raw.price_open',
            'raw.PriceOpen',
            'raw.price',
            'raw.Price',
            'raw.tradeData.openPrice',
            'raw.tradeData.price',
        ]);

        return $value !== null ? (float) $value : null;
    }

    private function tradeExitPriceValue(array $row): ?float
    {
        $value = $this->firstFilledValue($row, [
            'exit_price',
            'exitPrice',
            'close_price',
            'closePrice',
            'price_close',
            'priceClose',
            'PriceClose',
            'execution_price',
            'executionPrice',
            'close_rate',
            'closeRate',
            'raw.exit_price',
            'raw.exitPrice',
            'raw.close_price',
            'raw.closePrice',
            'raw.price_close',
            'raw.PriceClose',
            'raw.execution_price',
            'raw.executionPrice',
            'raw.tradeData.closePrice',
            'raw.tradeData.executionPrice',
        ]);

        return $value !== null ? (float) $value : null;
    }

    private function tradeOpenTimestamp(array $row): ?Carbon
    {
        foreach ([
            'open_timestamp',
            'open_time',
            'openTime',
            'openTimeMsc',
            'time',
            'Time',
            'time_open',
            'TimeOpen',
            'opened_at',
            'raw.openTimestamp',
            'raw.tradeData.openTimestamp',
            'raw.open_time',
            'raw.openTime',
            'raw.time',
        ] as $key) {
            $timestamp = $this->parseTradeTimestamp(Arr::get($row, $key));

            if ($timestamp instanceof Carbon) {
                return $timestamp;
            }
        }

        return null;
    }

    private function tradeCloseTimestamp(array $row): ?Carbon
    {
        foreach ([
            'close_timestamp',
            'closed_at',
            'close_time',
            'closeTime',
            'closeTimeMsc',
            'execution_timestamp',
            'execution_time',
            'executionTime',
            'time_close',
            'TimeClose',
            'raw.closeTimestamp',
            'raw.executionTimestamp',
            'raw.closeTime',
            'raw.executionTime',
        ] as $key) {
            $timestamp = $this->parseTradeTimestamp(Arr::get($row, $key));

            if ($timestamp instanceof Carbon) {
                return $timestamp;
            }
        }

        return null;
    }

    private function parseTradeTimestamp(mixed $value): ?Carbon
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value);
            }

            if (is_numeric($value)) {
                $numeric = (int) $value;

                return $numeric > 9999999999
                    ? Carbon::createFromTimestampMs($numeric)
                    : Carbon::createFromTimestamp($numeric);
            }

            if (is_string($value) && $value !== '') {
                return Carbon::parse($value);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function formatTradeDate(?Carbon $value): string
    {
        return $value?->locale(app()->getLocale())->translatedFormat('M d, Y H:i') ?? __('—');
    }

    private function formatTradeDuration(?Carbon $openedAt, ?Carbon $closedAt, bool $isOpen): ?string
    {
        if (! $openedAt instanceof Carbon) {
            return null;
        }

        $endedAt = $closedAt instanceof Carbon ? $closedAt : ($isOpen ? now() : null);

        if (! $endedAt instanceof Carbon || $endedAt->lt($openedAt)) {
            return null;
        }

        $seconds = $openedAt->diffInSeconds($endedAt);
        $days = (int) floor($seconds / 86400);
        $hours = (int) floor(($seconds % 86400) / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        if ($days > 0) {
            return sprintf('%dd %02dh', $days, $hours);
        }

        return sprintf('%02dh %02dm', $hours, $minutes);
    }

    private function formatPrice(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format($value, abs($value) < 10 ? 5 : 2, '.', ',');
    }

    private function formatNumber(?float $value, int $minDecimals = 0, int $maxDecimals = 5): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = rtrim(rtrim(number_format($value, $maxDecimals, '.', ''), '0'), '.');

        if (! str_contains($normalized, '.')) {
            return number_format($value, $minDecimals, '.', ',');
        }

        $decimals = strlen((string) str($normalized)->after('.'));
        $decimals = max($decimals, $minDecimals);

        return number_format($value, $decimals, '.', ',');
    }

    private function formatMoney(float $amount, string $currency = 'USD'): string
    {
        $formattedAmount = number_format(abs($amount), 2);
        $prefix = match (strtoupper($currency)) {
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        return ($amount < 0 ? '-' : '').$prefix.$formattedAmount;
    }

    private function metricTone(float $value): string
    {
        return match (true) {
            $value > 0.009 => 'emerald',
            $value < -0.009 => 'rose',
            default => 'slate',
        };
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'mt5_ea' => 'MT5 EA',
            'ctrader_api' => 'cTrader API',
            'platform_sync' => __('Platform Sync'),
            'snapshot_payload' => __('Snapshot payload'),
            default => str($source)->replace(['_', '-'], ' ')->title()->toString(),
        };
    }

    private function mergeTradeRows(array $primaryRows, array $secondaryRows): array
    {
        return $this->uniqueTradeRows(array_merge($primaryRows, $secondaryRows));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function uniqueTradeRows(array $rows): array
    {
        $seenIdentities = [];

        return collect($rows)
            ->filter(function (array $row) use (&$seenIdentities): bool {
                $identity = $this->tradeIdentityKey($row);

                if ($identity === null) {
                    return true;
                }

                if (isset($seenIdentities[$identity])) {
                    return false;
                }

                $seenIdentities[$identity] = true;

                return true;
            })
            ->values()
            ->all();
    }

    private function payloadDeclaresNoOpenRows(array $payload, array $openRows): bool
    {
        if ($openRows !== []) {
            return false;
        }

        if ($this->payloadHasAnyPath($payload, self::OPEN_POSITION_PATHS)) {
            return true;
        }

        $openCount = $this->payloadIntegerValue($payload, [
            'positions_count',
            'positionsCount',
            'open_positions_count',
            'openPositionsCount',
            'open_trades_count',
            'openTradesCount',
            'raw.positions_count',
            'raw.positionsCount',
            'raw.open_positions_count',
            'raw.openPositionsCount',
            'raw.open_trades_count',
            'raw.openTradesCount',
            'mt5.positions_count',
            'mt5.positionsCount',
            'mt5.open_positions_count',
            'mt5.openPositionsCount',
            'mt5.open_trades_count',
            'mt5.openTradesCount',
        ]);

        return $openCount === 0;
    }

    private function payloadHasAnyPath(array $payload, array $paths): bool
    {
        foreach ($paths as $path) {
            if (Arr::has($payload, $path)) {
                return true;
            }
        }

        return false;
    }

    private function missingTradeRowsMessage(array $payload): ?string
    {
        if (! $this->payloadReportsTradeActivity($payload)) {
            return null;
        }

        return __('MT5 sync is updating this account, but recent snapshots still do not include row-level open or closed trade rows. The account summary can refresh before detailed rows arrive, and this table only fills from real synced MT5 trade data.');
    }

    private function payloadReportsTradeActivity(array $payload): bool
    {
        if ($this->payloadBooleanValue($payload, [
            'has_activity',
            'hasActivity',
            'raw.has_activity',
            'raw.hasActivity',
            'mt5.has_activity',
            'mt5.hasActivity',
        ]) === true) {
            return true;
        }

        return ($this->payloadIntegerValue($payload, [
            'positions_count',
            'positionsCount',
            'closed_positions_count',
            'closedPositionsCount',
            'trade_count',
            'tradeCount',
            'activity_count',
            'activityCount',
            'raw.positions_count',
            'raw.positionsCount',
            'raw.closed_positions_count',
            'raw.closedPositionsCount',
            'raw.trade_count',
            'raw.tradeCount',
            'raw.activity_count',
            'raw.activityCount',
            'mt5.positions_count',
            'mt5.positionsCount',
            'mt5.closed_positions_count',
            'mt5.closedPositionsCount',
            'mt5.trade_count',
            'mt5.tradeCount',
            'mt5.activity_count',
            'mt5.activityCount',
        ]) ?? 0) > 0;
    }

    private function payloadIntegerValue(array $payload, array $keys): ?int
    {
        $value = $this->firstFilledValue($payload, $keys);

        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return max((int) $value, 0);
    }

    private function payloadBooleanValue(array $payload, array $keys): ?bool
    {
        $value = $this->firstFilledValue($payload, $keys);

        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (is_string($value)) {
            return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstFilledValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($row, $key);

            if (! blank($value)) {
                return $value;
            }
        }

        return null;
    }
}
