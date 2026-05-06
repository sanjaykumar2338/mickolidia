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

        $this->addConnectorSources($zip);
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

    private function addConnectorSources(ZipArchive $zip): void
    {
        $root = public_path('mt5software');
        $files = [
            'WolforixRuleEngineEA.mq5',
            'Include/WolforixDisplay.mqh',
            'Include/WolforixEngine.mqh',
            'Include/WolforixSync.mqh',
            'Include/WolforixTypes.mqh',
        ];

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
            '   Secret Token: included in wolforix-config.json',
            '6. Allow WebRequest for the Wolforix Base URL if MetaTrader 5 asks.',
            '',
            'Keep wolforix-config.json private. It contains your Secret Token.',
        ]).PHP_EOL;
    }

    private function safeReference(string $reference): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $reference) ?: 'account';

        return trim($safe, '-') ?: 'account';
    }
}
