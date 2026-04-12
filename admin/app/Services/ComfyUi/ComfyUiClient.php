<?php

namespace App\Services\ComfyUi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ComfyUiClient
{
    protected ErrorHandler $errorHandler;

    public function __construct(?ErrorHandler $errorHandler = null)
    {
        $this->errorHandler = $errorHandler ?? new ErrorHandler();
    }

    /**
     * Queue a prompt to ComfyUI.
     *
     * @param array $workflow The resolved workflow JSON
     * @param string $clientId The client ID (usually the generation job ID)
     * @return array The response containing prompt_id
     */
    public function queuePrompt(array $workflow, string $clientId): array
    {
        $jobId = is_numeric($clientId) ? (int) $clientId : null;

        return $this->retryRequest(function () use ($workflow, $clientId) {
            $response = Http::baseUrl(config('services.comfyui.base_url'))
                ->timeout(config('services.comfyui.timeout_seconds'))
                ->post('/prompt', [
                    'prompt' => $workflow,
                    'client_id' => $clientId,
                ]);

            if ($response->failed()) {
                Log::error("ComfyUI Queue Prompt Failed: HTTP {$response->status()}", [
                    'body' => $response->body(),
                    'clientId' => $clientId,
                ]);
            }

            $response->throw();

            return $response->json();
        }, 'queue prompt', 3, 1000, $jobId);
    }

    public function fetchHistory(string $promptId): array
    {
        return $this->retryRequest(function () use ($promptId) {
            $response = Http::baseUrl(config('services.comfyui.base_url'))
                ->timeout(config('services.comfyui.timeout_seconds'))
                ->get("/history/{$promptId}");

            $response->throw();

            return $response->json();
        }, 'fetch history');
    }

    public function fetchQueue(): array
    {
        return $this->retryRequest(function () {
            $response = Http::baseUrl(config('services.comfyui.base_url'))
                ->timeout(10)
                ->get('/queue');

            $response->throw();

            return $response->json();
        }, 'fetch queue');
    }

    public function uploadImage(string $filePath, ?string $overwriteFilename = null): array
    {
        return $this->retryRequest(function () use ($filePath, $overwriteFilename) {
            $fullPath = storage_path('app/public/' . $filePath);

            if (! file_exists($fullPath)) {
                throw new \Exception("图片文件不存在: {$filePath}");
            }

            $mimeType = mime_content_type($fullPath);
            $originalFilename = basename($filePath);

            $response = Http::baseUrl(config('services.comfyui.base_url'))
                ->timeout(60)
                ->attach('image', fopen($fullPath, 'r'), $overwriteFilename ?? $originalFilename, [
                    'Content-Type' => $mimeType,
                ])
                ->post('/upload/image', [
                    'type' => 'input',
                ]);

            if ($response->failed()) {
                Log::error("ComfyUI Image Upload Failed: HTTP {$response->status()}", [
                    'body' => $response->body(),
                    'filePath' => $filePath,
                ]);
            }

            $response->throw();

            return $response->json();
        }, 'upload image');
    }

    /**
     * @return mixed
     */
    private function retryRequest(callable $request, string $operation, int $maxAttempts = 3, int $retryDelay = 1000, ?int $jobId = null)
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = $request();
                $this->errorHandler->recordSuccess($operation);

                return $result;
            } catch (\Exception $e) {
                $lastException = $e;
                $errorHandling = $this->errorHandler->handleException($e, $operation, $jobId);

                if (! $errorHandling['should_retry'] || $attempt >= $maxAttempts) {
                    break;
                }

                $currentDelay = $errorHandling['delay_seconds'] * 1000;

                Log::warning("ComfyUI {$operation} 请求失败 (尝试 {$attempt}/{$maxAttempts}), {$errorHandling['message']}", [
                    'job_id' => $jobId,
                    'error_type' => $errorHandling['error_type'],
                    'delay_ms' => $currentDelay,
                ]);

                usleep($currentDelay * 1000);
            }
        }

        $finalMessage = "ComfyUI {$operation} 请求失败，已重试 {$maxAttempts} 次";
        if ($lastException) {
            $finalMessage .= ': ' . $lastException->getMessage();
        }

        throw new \Exception($finalMessage, 0, $lastException);
    }
}
