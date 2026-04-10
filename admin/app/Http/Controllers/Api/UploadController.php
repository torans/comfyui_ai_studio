<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ComfyUi\ComfyUiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * 文件上传控制器
 * 处理图片等文件上传，为图生图、图生视频等工作流提供上传能力
 */
class UploadController extends Controller
{
    public function __construct(
        private ComfyUiClient $comfyUiClient
    ) {
    }

    /**
     * 上传图片
     */
    public function image(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,gif,webp,bmp',
                'max:20480', // 20MB
            ],
        ]);

        $file = $request->file('file');

        // 生成唯一文件名
        $filename = sprintf(
            '%s_%s.%s',
            date('YmdHis'),
            bin2hex(random_bytes(8)),
            $file->getClientOriginalExtension()
        );

        // 按年月分目录存储
        $path = sprintf('uploads/%s/%s', date('Y/m'), $filename);

        // 存储文件
        $storedPath = Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if (!$storedPath) {
            return response()->json([
                'message' => '文件上传失败',
            ], 500);
        }

        return response()->json([
            'id' => null,
            'url' => Storage::url($storedPath),
            'path' => $storedPath,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ], 201);
    }

    /**
     * 上传图片到 Admin 后直接桥接上传到 ComfyUI
     */
    public function comfyImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,gif,webp,bmp',
                'max:20480',
            ],
        ]);

        $file = $request->file('file');

        $filename = sprintf(
            '%s_%s.%s',
            date('YmdHis'),
            bin2hex(random_bytes(8)),
            $file->getClientOriginalExtension()
        );

        $path = sprintf('uploads/%s/%s', date('Y/m'), $filename);

        $storedPath = Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if (!$storedPath) {
            return response()->json([
                'message' => '文件上传失败',
            ], 500);
        }

        $comfyUpload = $this->comfyUiClient->uploadImage($storedPath);
        $inputValue = $comfyUpload['name'] ?? $comfyUpload['filename'] ?? basename($storedPath);

        return response()->json([
            'id' => null,
            'url' => Storage::url($storedPath),
            'path' => $storedPath,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'comfyui' => [
                'name' => $comfyUpload['name'] ?? $inputValue,
                'subfolder' => $comfyUpload['subfolder'] ?? '',
                'type' => $comfyUpload['type'] ?? 'input',
            ],
            'input_value' => $inputValue,
        ], 201);
    }
}
