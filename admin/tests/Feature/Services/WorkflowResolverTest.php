<?php

namespace Tests\Feature\Services;

use App\Models\WorkflowTemplate;
use App\Services\Workflow\WorkflowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_injects_t2i_prompt(): void
    {
        $workflow = WorkflowTemplate::factory()->create([
            'type' => 't2i',
            'definition_json' => ['69' => ['inputs' => ['prompt' => 'placeholder']]],
        ]);

        $resolved = app(WorkflowResolver::class)->resolve($workflow, [
            'prompt' => '一台未来感咖啡机',
        ]);

        $this->assertSame('一台未来感咖啡机', $resolved['69']['inputs']['prompt']);
    }

    public function test_resolver_maps_aspect_ratio_to_width_and_height(): void
    {
        $workflow = WorkflowTemplate::factory()->create([
            'type' => 't2i',
            'definition_json' => [
                '64:13' => ['inputs' => ['width' => 1088, 'height' => 1088]],
            ],
            'parameter_schema_json' => [
                'aspect_ratio' => [
                    'field' => 'aspect_ratio',
                    'type' => 'select',
                    'default' => '1:1',
                    'presets' => [
                        '1:1' => ['width' => 1088, 'height' => 1088],
                        '16:9' => ['width' => 1344, 'height' => 768],
                    ],
                    'targets' => [
                        'width' => ['node' => '64:13', 'field' => 'width'],
                        'height' => ['node' => '64:13', 'field' => 'height'],
                    ],
                ],
            ],
        ]);

        $resolved = app(WorkflowResolver::class)->resolve($workflow, [
            'aspect_ratio' => '16:9',
        ]);

        $this->assertSame(1344, $resolved['64:13']['inputs']['width']);
        $this->assertSame(768, $resolved['64:13']['inputs']['height']);
    }
}
