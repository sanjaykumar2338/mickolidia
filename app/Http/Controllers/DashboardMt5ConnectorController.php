<?php

namespace App\Http\Controllers;

use App\Models\TradingAccount;
use App\Support\Mt5ConnectorCredentials;
use App\Support\Mt5ConnectorPackageBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardMt5ConnectorController extends Controller
{
    public function __invoke(
        Request $request,
        TradingAccount $account,
        Mt5ConnectorCredentials $connectorCredentials,
        Mt5ConnectorPackageBuilder $packageBuilder,
    ): BinaryFileResponse {
        abort_unless((int) $account->user_id === (int) $request->user()?->id, 403);

        $connector = $connectorCredentials->forAccount($account);
        $package = $packageBuilder->build($account->fresh() ?? $account, $connector);

        return response()
            ->download($package['path'], $package['filename'], [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ])
            ->deleteFileAfterSend(true);
    }
}
