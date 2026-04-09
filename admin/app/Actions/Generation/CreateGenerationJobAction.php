<?php

namespace App\Actions\Generation;

use App\Models\GenerationJob;
use App\Models\User;
use App\Models\WorkflowTemplate;

class CreateGenerationJobAction
{
    /**
     * 创建新的生成任务
     */
    public function handle(User $user, array $payload): GenerationJob
    {
        $workflow = WorkflowTemplate::where('id', $payload['workflow_id'])
            ->where('is_active', true)
            ->firstOrFail();

        return GenerationJob::create([
            'user_id' => $user->id,
            'workflow_template_id' => $workflow->id,
            'type' => $workflow->type,
            'status' => 'pending',
            'input_json' => $payload['inputs'],
        ]);
    }
}
