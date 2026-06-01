<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;

class MetaApiTodayProfitBreakdown
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function fromRows(array $rows, ?Carbon $serverDay = null): array
    {
        $serverDay ??= now();
        $gross = 0.0;
        $commission = 0.0;
        $swap = 0.0;
        $net = 0.0;
        $matchedRows = 0;

        foreach ($rows as $row) {
            if (! $this->isClosedTradingRow($row, $serverDay)) {
                continue;
            }

            $rowCommission = $this->firstNumber($row, [
                'commission_value',
                'commissionValue',
                'commission',
                'Commission',
                'raw.commission',
                'raw.Commission',
            ]) ?? 0.0;
            $rowSwap = $this->firstNumber($row, [
                'swap_value',
                'swapValue',
                'swap',
                'Swap',
                'raw.swap',
                'raw.Swap',
            ]) ?? 0.0;
            $explicitNet = $this->firstNumber($row, [
                'net_result_value',
                'netResultValue',
                'net_profit',
                'netProfit',
                'net_result',
                'netResult',
                'raw.net_profit',
                'raw.netProfit',
                'raw.net_result',
                'raw.netResult',
            ]);
            $rowGross = $this->firstNumber($row, [
                'profit_value',
                'profitValue',
                'gross_profit',
                'grossProfit',
                'profit',
                'Profit',
                'realizedProfit',
                'realized_profit',
                'raw.gross_profit',
                'raw.grossProfit',
                'raw.profit',
                'raw.Profit',
            ]);

            if ($rowGross === null && $explicitNet !== null) {
                $rowGross = round($explicitNet - $rowCommission - $rowSwap, 2);
            }

            $rowGross ??= 0.0;
            $rowNet = $explicitNet ?? round($rowGross + $rowCommission + $rowSwap, 2);

            $gross += $rowGross;
            $commission += $rowCommission;
            $swap += $rowSwap;
            $net += $rowNet;
            $matchedRows++;
        }

        return [
            'closed_trades_today_count' => $matchedRows,
            'gross_today_profit' => round($gross, 2),
            'today_commission' => round($commission, 2),
            'today_swap' => round($swap, 2),
            'net_today_profit' => round($net, 2),
            'source_of_today_profit' => 'today_closed_trades',
            'label' => 'Today Closed P/L',
            'includes_floating' => false,
            'server_day' => $serverDay->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function isClosedTradingRow(array $row, Carbon $serverDay): bool
    {
        $closedAt = $this->closedAt($row);

        if (! $closedAt instanceof Carbon || ! $closedAt->isSameDay($serverDay)) {
            return false;
        }

        return $this->isTradingHistoryRow($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function isTradingHistoryRow(array $row): bool
    {
        if ($this->isOpeningDeal($row)) {
            return false;
        }

        return $this->isTradingDeal($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isOpeningDeal(array $row): bool
    {
        $entryType = strtolower((string) $this->first($row, [
            'entryType',
            'entry_type',
            'raw.entryType',
            'raw.entry_type',
        ]));

        return $entryType !== '' && (str_contains($entryType, 'entry_in') || $entryType === 'in');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function closedAt(array $row): ?Carbon
    {
        foreach ([
            'close_at',
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
            'time',
            'Time',
            'timeMsc',
            'raw.close_at',
            'raw.closeTimestamp',
            'raw.executionTimestamp',
            'raw.closeTime',
            'raw.executionTime',
            'raw.time',
            'raw.Time',
            'raw.timeMsc',
        ] as $path) {
            $value = Arr::get($row, $path);
            $timestamp = $this->carbon($value);

            if ($timestamp instanceof Carbon) {
                return $timestamp;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isTradingDeal(array $row): bool
    {
        $type = strtolower((string) $this->first($row, [
            'type',
            'Type',
            'deal_type',
            'dealType',
            'entryType',
            'entry_type',
            'raw.type',
            'raw.Type',
            'raw.deal_type',
            'raw.dealType',
        ]));

        if ($type !== '') {
            if (str_contains($type, 'buy') || str_contains($type, 'sell')) {
                return true;
            }

            foreach (['balance', 'credit', 'charge', 'correction', 'bonus', 'commission', 'fee', 'tax', 'interest', 'dividend', 'agent'] as $excluded) {
                if (str_contains($type, $excluded)) {
                    return false;
                }
            }
        }

        $numericType = $this->number($this->first($row, ['type', 'Type', 'deal_type', 'dealType', 'raw.type', 'raw.Type']));

        if ($numericType !== null) {
            return in_array((int) $numericType, [0, 1], true);
        }

        return filled((string) $this->first($row, ['symbol', 'Symbol', 'raw.symbol', 'raw.Symbol']))
            && ($this->firstNumber($row, ['profit_value', 'net_result_value', 'profit', 'net_profit', 'net_result']) !== null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $paths
     */
    private function first(array $row, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($row, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $paths
     */
    private function firstNumber(array $row, array $paths): ?float
    {
        foreach ($paths as $path) {
            $number = $this->number(Arr::get($row, $path));

            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function carbon(mixed $value): ?Carbon
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value);
            }

            if (is_numeric($value)) {
                $timestamp = (int) $value;

                return $timestamp > 9999999999
                    ? Carbon::createFromTimestampMs($timestamp)
                    : Carbon::createFromTimestamp($timestamp);
            }

            if (is_string($value) && trim($value) !== '') {
                return Carbon::parse(trim($value));
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
