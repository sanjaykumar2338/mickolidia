<?php

namespace App\Http\Controllers;

use App\Services\Voice\OpenAiTextToSpeechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class VoiceAssistantSpeechController extends Controller
{
    public function __invoke(Request $request, OpenAiTextToSpeechService $speechService): Response|JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        if (! $speechService->isConfigured()) {
            return response()->json([
                'message' => __('site.contact.voice_audio_unavailable'),
            ], 503);
        }

        try {
            $speech = $speechService->synthesize(
                (string) $validated['text'],
                (string) ($validated['locale'] ?? '')
            );
        } catch (Throwable $error) {
            Log::warning('Wolfi speech synthesis failed.', [
                'locale' => $validated['locale'] ?? null,
                'message' => $error->getMessage(),
            ]);

            return response()->json([
                'message' => __('site.contact.voice_audio_unavailable'),
            ], 502);
        }

        return response($speech['audio'], 200, [
            'Content-Type' => $speech['content_type'],
            'Cache-Control' => 'no-store, max-age=0',
            'X-Wolfi-TTS-Provider' => 'openai',
            'X-Wolfi-TTS-Voice' => $speech['voice'],
            'X-Wolfi-TTS-Model' => $speech['model'],
            'X-Wolfi-TTS-Locale' => $speech['locale'],
        ]);
    }
}
