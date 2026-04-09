<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ComfyUi\ComfyUiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * ComfyUI 代理控制器
 * 客户端通过 Admin 后端代理访问 ComfyUI，避免跨域问题
 */
class ComfyUiProxyController extends Controller
{
    public function __construct(
        private ComfyUiClient $client
    ) {
    }

    /**
     * 检查 ComfyUI 服务状态
     */
    public function systemStats(): JsonResponse
    {
        try {
            $baseUrl = config('services.comfyui.base_url');
            $response = Http::baseUrl($baseUrl)
                ->timeout(5)
                ->get('/system_stats');

            if ($response->successful()) {
                return response()->json([
                    'online' => true,
                    'data' => $response->json(),
                ]);
            }

            return response()->json([
                'online' => false,
                'error' => 'ComfyUI 返回错误状态: ' . $response->status(),
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'online' => false,
                'error' => '无法连接到 ComfyUI: ' . $e->getMessage(),
            ], 503);
        }
    }

    /**
     * 获取可用的模型列表
     */
    public function models(): JsonResponse
    {
        try {
            $baseUrl = config('services.comfyui.base_url');
            $response = Http::baseUrl($baseUrl)
                ->timeout(10)
                ->get('/object_info');

            if (!$response->successful()) {
                return response()->json([
                    'models' => [],
                    'error' => '获取模型列表失败',
                ], 500);
            }

            $info = $response->json();
            $checkpoints = [];

            // 从 CheckpointLoaderSimple 提取 checkpoint 列表
            if (isset($info['CheckpointLoaderSimple'])) {
                $ckptNames = $info['CheckpointLoaderSimple']['input']['required']['ckpt_name'] ?? [];
                if (isset($ckptNames[0]) && is_array($ckptNames[0])) {
                    $checkpoints = $ckptNames[0];
                }
            }

            // 备用：从 any 类型中提取
            if (empty($checkpoints) && isset($info['CheckpointLoader'])) {
                $ckptNames = $info['CheckpointLoader']['input']['required']['ckpt_name'] ?? [];
                if (isset($ckptNames[0]) && is_array($ckptNames[0])) {
                    $checkpoints = $ckptNames[0];
                }
            }

            return response()->json([
                'models' => $checkpoints,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'models' => [],
                'error' => '获取模型列表失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 上传图片到 ComfyUI
     * 客户端先上传图片到 Admin，然后通过此接口转发到 ComfyUI
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'], // storage_path，如 "uploads/2026/04/image.png"
        ]);

        try {
            $result = $this->client->uploadImage($request->path);

            return response()->json([
                'name' => $result['name'] ?? '',
                'filename' => $result['filename'] ?? '',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => '上传图片到 ComfyUI 失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取工作流详情（用于调试）
     */
    public function history(string $promptId): JsonResponse
    {
        try {
            $history = $this->client->fetchHistory($promptId);

            return response()->json([
                'prompt_id' => $promptId,
                'history' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => '获取历史记录失败: ' . $e->getMessage(),
            ], 500);
        }
    }
}
