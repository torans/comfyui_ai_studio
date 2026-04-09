<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GenerationAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * 文件上传控制器
 * 处理图片等文件上传，为图生图、图生视频等工作流提供上传能力
 */
class UploadController extends Controller
{
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

        // 创建生成资源记录
        $asset = GenerationAsset::create([
            'user_id' => $request->user()->id,
            'generation_job_id' => null,
            'type' => 'source',
            'filename' => $file->getClientOriginalName(),
            'storage_disk' => 'public',
            'storage_path' => $storedPath,
        ]);

        return response()->json([
            'id' => $asset->id,
            'url' => Storage::url($storedPath),
            'path' => $storedPath,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ], 201);
    }
}
