<?php

namespace App\Services\ComfyUi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\ComfyUi\ErrorHandler;

class ComfyUiClient
{
    protected ErrorHandler $errorHandler;

    public function __construct(ErrorHandler $errorHandler = null)
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

            $response->throw();

            return $response->json();
        }, 'queue prompt', 3, 1000, $jobId);
    }

    /**
     * Fetch the history of a prompt from ComfyUI.
     *
     * @param string $promptId The prompt ID to fetch history for
     * @return array The history data
     */
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

    /**
     * Fetch the current queue from ComfyUI.
     *
     * @return array The queue data containing queue_pending and queue_running
     */
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

    /**
     * Upload an image file to ComfyUI.
     *
     * @param string $filePath 本地文件路径（相对于 storage/app/public）
     * @param string $overwriteFilename 覆盖的文件名（可选）
     * @return array 上传结果，包含 name, filename 等
     */
    public function uploadImage(string $filePath, ?string $overwriteFilename = null): array
    {
        return $this->retryRequest(function () use ($filePath, $overwriteFilename) {
            $fullPath = storage_path('app/public/' . $filePath);

            if (!file_exists($fullPath)) {
                throw new \Exception("图片文件不存在: {$filePath}");
            }

            $mimeType = mime_content_type($fullPath);
            $originalFilename = basename($filePath);

            $response = Http::baseUrl(config('services.comfyui.base_url'))
                ->timeout(60)
                ->attach('image', fopen($fullPath, 'r'), $overwriteFilename ?? $originalFilename, [
                    'Content-Type' => $mimeType,
                ])
                ->post('/upload/image');

            $response->throw();

            return $response->json();
        }, 'upload image');
    }

    /**
     * 重试请求方法，处理网络不稳定情况
     *
     * @param callable $request 请求闭包
     * @param string $operation 操作名称，用于日志
     * @param int $maxAttempts 最大重试次数
     * @param int $retryDelay 重试延迟（毫秒）
     * @param int|null $jobId 关联的任务ID（可选）
     * @return mixed
     * @throws \Exception
     */
    private function retryRequest(callable $request, string $operation, int $maxAttempts = 3, int $retryDelay = 1000, ?int $jobId = null)
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = $request();
                
                // 请求成功，记录统计
                $this->errorHandler->recordSuccess($operation);
                
                return $result;
            } catch (\Exception $e) {
                $lastException = $e;
                
                // 使用错误处理器分析错误
                $errorHandling = $this->errorHandler->handleException($e, $operation, $jobId);
                
                // 根据错误处理器决定是否继续重试
                if (!$errorHandling['should_retry'] || $attempt >= $maxAttempts) {
                    break;
                }
                
                // 使用错误处理器建议的延迟时间
                $currentDelay = $errorHandling['delay_seconds'] * 1000; // 转换为毫秒
                
                Log::warning("ComfyUI {$operation} 请求失败 (尝试 {$attempt}/{$maxAttempts}), {$errorHandling['message']}", [
                    'job_id' => $jobId,
                    'error_type' => $errorHandling['error_type'],
                    'delay_ms' => $currentDelay,
                ]);
                
                // 等待后重试
                usleep($currentDelay * 1000);
            }
        }
        
        // 所有重试都失败
        $finalMessage = "ComfyUI {$operation} 请求失败，已重试 {$maxAttempts} 次";
        if ($lastException) {
            $finalMessage .= ": " . $lastException->getMessage();
        }
        
        throw new \Exception($finalMessage, 0, $lastException);
    }
}
