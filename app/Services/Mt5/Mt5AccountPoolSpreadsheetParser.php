<?php

namespace App\Services\Mt5;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class Mt5AccountPoolSpreadsheetParser
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(string $path): array
    {
        $resolvedPath = $this->resolvePath($path);
        $dom = $this->loadDom($resolvedPath);
        $xpath = new DOMXPath($dom);
        $this->registerNamespaces($xpath);

        /** @var DOMNodeList<DOMElement> $tables */
        $tables = $xpath->query('//table:table');

        foreach ($tables as $table) {
            $rows = $this->tableRows($xpath, $table);
            $inspection = $this->inspectRows($rows);

            if ($inspection !== null) {
                return array_merge([
                    'path' => $resolvedPath,
                    'file' => basename($resolvedPath),
                    'sheet_name' => (string) $table->getAttribute('table:name'),
                ], $inspection);
            }
        }

        throw new RuntimeException('Could not find a supported MT5 account header row in the ODS file.');
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('The ODS path is required.');
        }

        $resolvedPath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        if (! is_file($resolvedPath)) {
            throw new RuntimeException(sprintf('ODS file not found: %s', $resolvedPath));
        }

        return $resolvedPath;
    }

    private function loadDom(string $path): DOMDocument
    {
        $archive = new ZipArchive();

        if ($archive->open($path) !== true) {
            throw new RuntimeException(sprintf('Unable to open ODS archive: %s', $path));
        }

        $contentXml = $archive->getFromName('content.xml');
        $archive->close();

        if (! is_string($contentXml) || trim($contentXml) === '') {
            throw new RuntimeException('The ODS file does not contain a readable content.xml payload.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($contentXml)) {
            throw new RuntimeException('The ODS content.xml payload could not be parsed.');
        }

        return $dom;
    }

    private function registerNamespaces(DOMXPath $xpath): void
    {
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
    }

    /**
     * @return list<array{row_number:int, values:list<string>}>
     */
    private function tableRows(DOMXPath $xpath, DOMElement $table): array
    {
        /** @var DOMNodeList<DOMElement> $rowNodes */
        $rowNodes = $xpath->query('./table:table-row', $table);
        $rows = [];
        $rowNumber = 1;

        foreach ($rowNodes as $rowNode) {
            $repeatRows = max((int) ($rowNode->getAttribute('table:number-rows-repeated') ?: 1), 1);
            $values = $this->rowValues($xpath, $rowNode);
            $hasContent = $this->rowHasContent($values);

            if (! $hasContent && $repeatRows > 1000) {
                break;
            }

            $copies = $hasContent ? $repeatRows : min($repeatRows, 1);

            for ($index = 0; $index < $copies; $index++) {
                $rows[] = [
                    'row_number' => $rowNumber + $index,
                    'values' => $values,
                ];
            }

            $rowNumber += $repeatRows;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function rowValues(DOMXPath $xpath, DOMElement $rowNode): array
    {
        /** @var DOMNodeList<DOMElement> $cellNodes */
        $cellNodes = $xpath->query('./table:table-cell|./table:covered-table-cell', $rowNode);
        $values = [];

        foreach ($cellNodes as $cellNode) {
            $repeatColumns = max((int) ($cellNode->getAttribute('table:number-columns-repeated') ?: 1), 1);
            $value = $this->cellValue($xpath, $cellNode);

            for ($index = 0; $index < $repeatColumns; $index++) {
                $values[] = $value;

                if (count($values) >= 32) {
                    break 2;
                }
            }
        }

        return $values;
    }

    private function cellValue(DOMXPath $xpath, DOMElement $cellNode): string
    {
        /** @var DOMNodeList<DOMElement> $paragraphNodes */
        $paragraphNodes = $xpath->query('.//text:p', $cellNode);
        $fragments = [];

        foreach ($paragraphNodes as $paragraphNode) {
            $text = trim((string) $paragraphNode->textContent);

            if ($text !== '') {
                $fragments[] = $text;
            }
        }

        if ($fragments !== []) {
            return implode("\n", $fragments);
        }

        foreach (['office:string-value', 'office:value', 'office:date-value'] as $attribute) {
            $value = $cellNode->getAttribute($attribute);

            if ($value !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param  list<array{row_number:int, values:list<string>}>  $rows
     * @return array<string, mixed>|null
     */
    private function inspectRows(array $rows): ?array
    {
        $headerRow = null;
        $metadata = [];

        foreach ($rows as $row) {
            if ($this->rowHasContent($row['values']) && $this->isHeaderRow($row['values'])) {
                $headerRow = $row;
                break;
            }

            $metadata = array_merge($metadata, $this->metadataFromRow($row['values']));
        }

        if ($headerRow === null) {
            return null;
        }

        $columnMap = $this->columnMap($headerRow['values']);
        $fieldMap = [];

        foreach ($columnMap as $column) {
            if ($column['field'] !== null) {
                $fieldMap[$column['field']] = $column['index'] - 1;
            }
        }

        $dataRows = [];
        $blankRows = 0;

        foreach ($rows as $row) {
            if ($row['row_number'] <= $headerRow['row_number']) {
                continue;
            }

            if (! $this->rowHasContent($row['values'])) {
                $blankRows++;

                if ($blankRows >= 3) {
                    break;
                }

                continue;
            }

            $blankRows = 0;
            $dataRows[] = [
                'row_number' => $row['row_number'],
                'cells' => $row['values'],
                'values' => $this->mappedRowValues($row['values'], $fieldMap),
            ];
        }

        return [
            'header_row_number' => $headerRow['row_number'],
            'column_map' => $columnMap,
            'field_map' => $fieldMap,
            'metadata' => $metadata,
            'rows' => $dataRows,
        ];
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function metadataFromRow(array $values): array
    {
        $metadata = [];

        foreach ($values as $value) {
            $trimmed = trim($value);

            if ($trimmed === '' || ! str_contains($trimmed, ':')) {
                continue;
            }

            [$key, $itemValue] = array_pad(explode(':', $trimmed, 2), 2, '');
            $key = Str::snake((string) Str::of($key)->lower()->squish()->replace('-', ' '));
            $itemValue = trim($itemValue);

            if ($key !== '' && $itemValue !== '') {
                $metadata[$key] = $itemValue;
            }
        }

        return $metadata;
    }

    /**
     * @param  list<string>  $values
     */
    private function isHeaderRow(array $values): bool
    {
        $normalized = array_map(fn (string $value): ?string => $this->canonicalField($value), $values);
        $recognized = array_values(array_filter($normalized));

        return in_array('login', $recognized, true)
            && in_array('password', $recognized, true)
            && in_array('server', $recognized, true)
            && in_array('account_size', $recognized, true);
    }

    /**
     * @param  list<string>  $values
     * @return list<array{index:int, letter:string, label:string, field:?string}>
     */
    private function columnMap(array $values): array
    {
        $columns = [];

        foreach ($values as $index => $value) {
            $columns[] = [
                'index' => $index + 1,
                'letter' => $this->columnLetter($index + 1),
                'label' => trim($value),
                'field' => $this->canonicalField($value),
            ];
        }

        return $columns;
    }

    /**
     * @param  list<string>  $values
     * @param  array<string, int>  $fieldMap
     * @return array<string, string>
     */
    private function mappedRowValues(array $values, array $fieldMap): array
    {
        $mapped = [];

        foreach ($fieldMap as $field => $index) {
            $mapped[$field] = trim((string) ($values[$index] ?? ''));
        }

        return $mapped;
    }

    private function canonicalField(string $value): ?string
    {
        $normalized = (string) Str::of($value)
            ->lower()
            ->squish()
            ->replace(['-', '_'], ' ')
            ->trim();

        return match ($normalized) {
            'login', 'account login' => 'login',
            'password', 'trading password' => 'password',
            'server', 'mt5 server' => 'server',
            'account size', 'size' => 'account_size',
            'status', 'available' => 'source_status',
            'c', 'currency', 'currency symbol' => 'currency_symbol',
            'created date', 'created at', 'date created' => 'source_created_at',
            default => null,
        };
    }

    /**
     * @param  list<string>  $values
     */
    private function rowHasContent(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function columnLetter(int $index): string
    {
        $letters = '';

        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }
}
