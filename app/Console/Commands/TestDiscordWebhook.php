<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestDiscordWebhook extends Command
{
    protected $signature = 'wolforix:test-discord-webhook
        {--send : Send a test payload when the webhook is configured and enabled}
        {--dry-run : Validate provider readiness without sending}
        {--json : Print JSON output}';

    protected $description = 'Check Phase 2 Discord webhook readiness without exposing webhook secrets.';

    public function handle(): int
    {
        $enabled = (bool) config('services.metaapi.events.discord_enabled', false);
        $url = (string) config('services.metaapi.events.discord_webhook_url', '');
        $configured = filled($url);
        $send = (bool) $this->option('send');
        $dryRun = (bool) $this->option('dry-run') || ! $send;
        $result = [
            'provider' => 'discord',
            'prepared' => true,
            'enabled' => $enabled,
            'configured' => $configured,
            'dry_run' => $dryRun,
            'sent' => false,
            'status' => $dryRun ? 'dry_run' : 'not_sent',
            'message' => $dryRun
                ? 'Discord webhook provider readiness validated without sending.'
                : 'Discord webhook provider is prepared. Use --send only after enabling/configuring it.',
        ];

        if ($send && ! $dryRun && $enabled && $configured) {
            $response = Http::timeout(10)->post($url, [
                'content' => 'Wolforix Phase 2 webhook readiness test. No account secrets are included.',
            ]);

            $result['sent'] = $response->successful();
            $result['status'] = $response->successful() ? 'sent' : 'failed';
            $result['http_status'] = $response->status();
            $result['message'] = $response->successful()
                ? 'Discord webhook test sent.'
                : 'Discord webhook returned a non-success status.';
        }

        $this->info('Discord webhook readiness');
        $this->table(['Field', 'Value'], [
            ['Prepared', ! empty($result['prepared']) ? 'yes' : 'no'],
            ['Enabled', ! empty($result['enabled']) ? 'yes' : 'no'],
            ['Configured', ! empty($result['configured']) ? 'yes' : 'no'],
            ['Dry run', ! empty($result['dry_run']) ? 'yes' : 'no'],
            ['Sent', ! empty($result['sent']) ? 'yes' : 'no'],
            ['Status', (string) $result['status']],
            ['Message', (string) $result['message']],
        ]);

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[result unavailable]');
        }

        return self::SUCCESS;
    }
}
