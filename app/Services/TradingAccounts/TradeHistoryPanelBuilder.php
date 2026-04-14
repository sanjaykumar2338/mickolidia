<?php

namespace App\Services\TradingAccounts;

use App\Models\TradingAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class TradeHistoryPanelBuilder
{
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
            'message' => (string) ($options['available_message'] ?? __('The latest synced snapshot powers this table. Open and closed rows are shown only when the platform payload includes them.')),
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
            ? ['position_id', 'positionId', 'ticket', 'Ticket', 'order', 'Order', 'id']
            : ['deal_id', 'dealId', 'position_id', 'positionId', 'ticket', 'Ticket', 'order', 'Order', 'id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function latestSnapshotTradePayload(TradingAccount $account): array
    {
        $latestSnapshot = $account->balanceSnapshots()
            ->latest('snapshot_at')
            ->first(['snapshot_at', 'payload']);

        $payload = is_array($latestSnapshot?->payload) ? $latestSnapshot->payload : [];

        return [
            'snapshot_at' => $latestSnapshot?->snapshot_at,
            'open_positions' => $this->tradeRowsFromPayload($payload, [
                'open_positions',
                'openPositions',
                'open.positions',
                'positions.open',
                'positions',
                'current_positions',
                'currentPositions',
                'raw.open_positions',
                'raw.openPositions',
                'raw.open.positions',
                'raw.positions.open',
                'raw.positions',
                'raw.current_positions',
                'mt5.open_positions',
                'mt5.positions',
            ]),
            'trade_history' => $this->tradeRowsFromPayload($payload, [
                'trade_history',
                'tradeHistory',
                'closed_trades',
                'closedTrades',
                'closed_positions',
                'closedPositions',
                'positions.closed',
                'history',
                'deal_history',
                'dealHistory',
                'deals',
                'orders',
                'raw.trade_history',
                'raw.tradeHistory',
                'raw.closed_trades',
                'raw.closedTrades',
                'raw.closed_positions',
                'raw.positions.closed',
                'raw.history',
                'raw.deal_history',
                'raw.deals',
                'raw.orders',
                'mt5.trade_history',
                'mt5.history',
                'mt5.deals',
            ], true),
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

        $seenIdentities = [];

        return $rows
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

        foreach (['data', 'items', 'rows', 'records', 'positions', 'deals', 'orders', 'history'] as $key) {
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
            'position_id',
            'positionId',
            'ticket',
            'Ticket',
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

        return match (true) {
            $normalized === '0',
            $normalized === '1',
            str_contains($normalized, 'buy'),
            str_contains($normalized, 'long') => __('Buy'),
            $normalized === '2',
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
