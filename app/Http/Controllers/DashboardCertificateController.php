<?php

namespace App\Http\Controllers;

use App\Models\TradingAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardCertificateController extends Controller
{
    public function __invoke(Request $request, TradingAccount $account): StreamedResponse
    {
        abort_unless((int) $account->user_id === (int) $request->user()?->id, 403);

        $path = (string) ($account->certificate_path ?? '');

        abort_if($path === '', 404);

        $disk = Storage::disk('public');

        abort_if(! $disk->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'png';
        $filename = 'wolforix-certificate-'.Str::slug((string) ($account->account_reference ?: $account->id)).'.'.$extension;
        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';

        return $disk->download($path, $filename, [
            'Content-Type' => $mimeType,
        ]);
    }
}
