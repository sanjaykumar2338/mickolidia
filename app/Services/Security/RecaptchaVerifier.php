<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecaptchaVerifier
{
    public function validate(Request $request, string $errorBag = 'default', ?string $action = null): void
    {
        if ($this->verify($request, $action)) {
            return;
        }

        $exception = ValidationException::withMessages([
            'g-recaptcha-response' => __('site.auth.recaptcha.failed'),
        ]);

        $exception->errorBag($errorBag);

        throw $exception;
    }

    public function verify(Request $request, ?string $action = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $token = trim((string) $request->input('g-recaptcha-response', ''));
        $secretKey = $this->secretKey();

        if ($token === '' || $secretKey === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeout())
                ->post($this->verifyUrl(), [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (Throwable $exception) {
            Log::warning('Google reCAPTCHA verification request failed.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('Google reCAPTCHA verification returned an unsuccessful HTTP response.', [
                'status' => $response->status(),
            ]);

            return false;
        }

        $result = $response->json();

        if (! (bool) data_get($result, 'success', false)) {
            return false;
        }

        if (! $this->usesScore()) {
            return true;
        }

        if ($action !== null && (string) data_get($result, 'action', '') !== $action) {
            Log::warning('Google reCAPTCHA verification returned an unexpected action.', [
                'expected_action' => $action,
                'actual_action' => data_get($result, 'action'),
            ]);

            return false;
        }

        return (float) data_get($result, 'score', 0) >= $this->scoreThreshold();
    }

    public function enabled(): bool
    {
        return (bool) config('services.recaptcha.enabled', false);
    }

    private function secretKey(): string
    {
        return trim((string) config('services.recaptcha.secret_key', ''));
    }

    private function usesScore(): bool
    {
        return $this->type() === 'v3';
    }

    private function type(): string
    {
        return strtolower(trim((string) config('services.recaptcha.type', 'v3'))) ?: 'v3';
    }

    private function scoreThreshold(): float
    {
        return max(0, min(1, (float) config('services.recaptcha.score_threshold', 0.5)));
    }

    private function verifyUrl(): string
    {
        return trim((string) config('services.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify'));
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.recaptcha.timeout', 5));
    }
}
