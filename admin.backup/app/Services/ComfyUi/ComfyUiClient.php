<?php

namespace App\Services\ComfyUi;

use Illuminate\Support\Facades\Http;

class ComfyUiClient
{
    public function queuePrompt(array $workflow, string $clientId): array
    {
        $response = Http::baseUrl(config('services.comfyui.base_url'))
            ->timeout(config('services.comfyui.timeout_seconds', 60))
            ->post('/prompt', [
                'prompt' => $workflow,
                'client_id' => $clientId,
            ]);

        return $response->json();
    }

    public function fetchHistory(string $promptId): array
    {
        $response = Http::baseUrl(config('services.comfyui.base_url'))
            ->timeout(config('services.comfyui.timeout_seconds', 60))
            ->get("/history/{$promptId}");

        return $response->json();
    }
}
