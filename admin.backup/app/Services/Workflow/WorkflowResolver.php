<?php

namespace App\Services\Workflow;

use App\Models\WorkflowTemplate;

class WorkflowResolver
{
    public function resolve(WorkflowTemplate $template, array $inputs): array
    {
        $workflow = $template->definition_json;

        if ($template->type === 't2i' && isset($workflow['69']['inputs']['prompt'])) {
            $workflow['69']['inputs']['prompt'] = $inputs['prompt'] ?? '';
        }

        if ($template->type === 'i2i' && isset($workflow['6']['inputs']['text'])) {
            $workflow['6']['inputs']['text'] = $inputs['prompt'] ?? '';
        }

        if ($template->type === 'i2v' && isset($workflow['6']['inputs']['prompt'])) {
            $workflow['6']['inputs']['prompt'] = $inputs['prompt'] ?? '';
        }

        if (isset($inputs['aspect_ratio'])) {
            $ratio = $this->parseAspectRatio($inputs['aspect_ratio']);
            foreach ($workflow as $nodeId => &$node) {
                if (isset($node['class_type']) && str_contains($node['class_type'], 'LatentImage')) {
                    $node['inputs']['width'] = $ratio['width'];
                    $node['inputs']['height'] = $ratio['height'];
                }
            }
        }

        return $workflow;
    }

    private function parseAspectRatio(string $ratio): array
    {
        return match ($ratio) {
            '1:1' => ['width' => 1024, 'height' => 1024],
            '3:4' => ['width' => 768, 'height' => 1024],
            '4:3' => ['width' => 1024, 'height' => 768],
            '16:9' => ['width' => 1280, 'height' => 720],
            '9:16' => ['width' => 720, 'height' => 1280],
            default => ['width' => 1024, 'height' => 1024],
        };
    }
}
