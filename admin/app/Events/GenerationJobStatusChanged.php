<?php

namespace App\Events;

use App\Models\GenerationJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 任务状态变更事件
 * 通过 WebSocket 广播到客户端
 */
class GenerationJobStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GenerationJob $job,
        public ?int $progress = null,
        public ?string $message = null
    ) {
    }

    /**
     * 获取广播频道
     */
    public function broadcastOn(): array
    {
        // 私有无频道，仅允许认证用户且是其本人的任务
        return [
            new PrivateChannel('user.' . $this->job->user_id),
        ];
    }

    /**
     * 广播事件名称
     */
    public function broadcastAs(): string
    {
        return 'generation-job.status-changed';
    }

    public function broadcastWith(): array
    {
        // 按照 ID 倒序获取最新的一个事件，确保进度是最新的
        $latestEvent = $this->job->events()->orderByDesc('id')->first();
        
        $assets = collect();
        if ($this->job->status === 'succeeded') {
            $assets = $this->job->assets->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'type' => $asset->type,
                    'media_kind' => $asset->media_kind,
                    'filename' => $asset->filename,
                    'url' => $asset->resolved_url,
                ];
            });
        }

        return [
            'id' => $this->job->id,
            'status' => $this->job->status,
            'type' => $this->job->type,
            'workflow_code' => $this->job->workflowTemplate?->code,
            'workflow_name' => $this->job->workflowTemplate?->name,
            'error_message' => $this->job->error_message,
            'started_at' => $this->job->started_at?->toISOString(),
            'finished_at' => $this->job->finished_at?->toISOString(),
            'progress' => $this->progress ?? $latestEvent?->progress ?? 0,
            'message' => $this->message ?? $latestEvent?->message ?? '',
            'assets' => $assets,
            'input_json' => $this->job->input_json,
        ];
    }
}
