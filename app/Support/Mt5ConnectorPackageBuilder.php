<?php

namespace App\Support;

use App\Models\TradingAccount;
use RuntimeException;
use ZipArchive;

class Mt5ConnectorPackageBuilder
{
    /**
     * @param  array{
     *     base_url:string,
     *     endpoint_url:string,
     *     account_reference:string,
     *     secret_token:string,
     *     download_url:string,
     *     download_file_name:string,
     *     status:string,
     *     status_label:string
     * }  $connector
     * @return array{path: string, filename: string}
     */
    public function build(TradingAccount $account, array $connector): array
    {
        $tmpDir = storage_path('app/tmp/mt5-connectors');

        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0755, true) && ! is_dir($tmpDir)) {
            throw new RuntimeException('Unable to create MT5 connector temp directory.');
        }

        $reference = $this->safeReference((string) $account->account_reference);
        $filename = "Wolforix-MT5-Connector-{$reference}.zip";
        $path = tempnam($tmpDir, 'wolforix-mt5-');

        if ($path === false) {
            throw new RuntimeException('Unable to create MT5 connector temp file.');
        }

        $zipPath = $path.'.zip';
        rename($path, $zipPath);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create MT5 connector package.');
        }

        $this->addConnectorSources($zip, $connector);
        $zip->addFromString('WolforixRuleEngineEA-'.$reference.'.set', $this->settingsFile($connector));
        $zip->addFromString('wolforix-config.json', json_encode([
            'base_url' => $connector['base_url'],
            'account_reference' => $connector['account_reference'],
            'secret_token' => $connector['secret_token'],
            'account_login' => filled($account->platform_login) ? (string) $account->platform_login : null,
            'endpoint_url' => $connector['endpoint_url'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('README-Wolforix-MT5-Connector.txt', $this->readme($connector));
        $zip->close();

        return [
            'path' => $zipPath,
            'filename' => $filename,
        ];
    }

    /**
     * @param  array{base_url:string, account_reference:string, secret_token:string}  $connector
     */
    private function addConnectorSources(ZipArchive $zip, array $connector): void
    {
        $root = public_path('mt5software');
        $files = [
            'Include/WolforixDisplay.mqh',
            'Include/WolforixEngine.mqh',
            'Include/WolforixSync.mqh',
            'Include/WolforixTypes.mqh',
        ];
        $eaPath = $root.DIRECTORY_SEPARATOR.'WolforixRuleEngineEA.mq5';

        if (! is_file($eaPath)) {
            throw new RuntimeException('Missing MT5 connector source file: WolforixRuleEngineEA.mq5');
        }

        $zip->addFromString('WolforixRuleEngineEA.mq5', $this->preconfiguredEaSource((string) file_get_contents($eaPath), $connector));

        foreach ($files as $relativePath) {
            $sourcePath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (! is_file($sourcePath)) {
                throw new RuntimeException("Missing MT5 connector source file: {$relativePath}");
            }

            $zip->addFile($sourcePath, $relativePath);
        }
    }

    /**
     * @param  array{base_url:string, account_reference:string, secret_token:string}  $connector
     */
    private function readme(array $connector): string
    {
        return implode(PHP_EOL, [
            'Wolforix MT5 Connector',
            '======================',
            '',
            'This package is preconfigured for your Wolforix account.',
            '',
            'Install:',
            '1. Copy WolforixRuleEngineEA.mq5 into MQL5/Experts.',
            '2. Copy the Include folder files into MQL5/Include.',
            '3. Restart MetaTrader 5 or refresh Expert Advisors.',
            '4. Attach WolforixRuleEngineEA to an MT5 chart.',
            '5. If prompted, confirm these values in the EA settings:',
            "   Base URL: {$connector['base_url']}",
            "   Account Reference: {$connector['account_reference']}",
            '   Secret Token: already prefilled in the EA input defaults and included in wolforix-config.json',
            '   You can also click Load in the EA Inputs tab and select the included .set file.',
            '6. In MetaTrader 5, open Tools > Options > Expert Advisors and add this URL to Allow WebRequest:',
            '   '.$this->webRequestOrigin($connector['base_url']),
            '',
            'Keep wolforix-config.json private. It contains your Secret Token.',
        ]).PHP_EOL;
    }

    /**
     * @param  array{base_url:string, account_reference:string, secret_token:string}  $connector
     */
    private function preconfiguredEaSource(string $source, array $connector): string
    {
        $replacements = [
            'ApiBaseUrl' => $connector['base_url'],
            'ApiToken' => $connector['secret_token'],
            'AccountReference' => $connector['account_reference'],
        ];

        foreach ($replacements as $input => $value) {
            $pattern = '/(input\s+string\s+'.$input.'\s*=\s*")[^"]*(";)/';
            $source = preg_replace_callback(
                $pattern,
                fn (array $matches): string => $matches[1].$this->mqlString($value).$matches[2],
                $source,
            ) ?? $source;
        }

        return $source;
    }

    /**
     * @param  array{base_url:string, account_reference:string, secret_token:string}  $connector
     */
    private function settingsFile(array $connector): string
    {
        return implode(PHP_EOL, [
            'ApiBaseUrl='.$connector['base_url'],
            'ApiToken='.$connector['secret_token'],
            'AccountReference='.$connector['account_reference'],
            'EnableSync=true',
        ]).PHP_EOL;
    }

    private function mqlString(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function webRequestOrigin(string $baseUrl): string
    {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || $scheme === '' || $host === '') {
            return rtrim($baseUrl, '/');
        }

        $origin = $scheme.'://'.$host;
        $port = parse_url($baseUrl, PHP_URL_PORT);

        if (is_int($port)) {
            $origin .= ':'.$port;
        }

        return $origin;
    }

    private function safeReference(string $reference): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $reference) ?: 'account';

        return trim($safe, '-') ?: 'account';
    }
}
