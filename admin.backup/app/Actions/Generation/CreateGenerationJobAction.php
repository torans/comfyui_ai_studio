<?php

namespace App\Actions\Generation;

use App\Models\GenerationJob;
use App\Models\User;
use App\Models\WorkflowTemplate;

class CreateGenerationJobAction
{
    public function handle(User $user, array $payload): GenerationJob
    {
        $workflow = WorkflowTemplate::where('code', $payload['workflow_code'])
            ->where('type', $payload['type'])
            ->where('is_active', true)
            ->firstOrFail();

        return GenerationJob::create([
            'user_id' => $user->id,
            'workflow_template_id' => $workflow->id,
            'type' => $payload['type'],
            'status' => 'pending',
            'input_json' => $payload['inputs'],
        ]);
    }
}
