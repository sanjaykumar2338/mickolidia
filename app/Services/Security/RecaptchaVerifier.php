<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecaptchaVerifier
{
    public function validate(Request $request, string $errorBag = 'default'): void
    {
        if ($this->verify($request)) {
            return;
        }

        $exception = ValidationException::withMessages([
            'g-recaptcha-response' => __('site.auth.recaptcha.failed'),
        ]);

        $exception->errorBag($errorBag);

        throw $exception;
    }

    public function verify(Request $request): bool
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

        return (bool) data_get($response->json(), 'success', false);
    }

    public function enabled(): bool
    {
        return (bool) config('services.recaptcha.enabled', false);
    }

    private function secretKey(): string
    {
        return trim((string) config('services.recaptcha.secret_key', ''));
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
