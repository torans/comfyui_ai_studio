<?php

namespace Tests\Feature\Services;

use App\Models\WorkflowTemplate;
use App\Services\Workflow\WorkflowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowResolverDuplicateInputKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_prefers_unique_input_keys_when_field_names_repeat(): void
    {
        $workflow = WorkflowTemplate::factory()->create([
            'type' => 'i2i',
            'definition_json' => [
                '11' => ['inputs' => ['image' => 'old-a.png']],
                '12' => ['inputs' => ['image' => 'old-b.png']],
            ],
            'parameter_schema_json' => [
                [
                    'node' => '11',
                    'field' => 'image',
                    'input_key' => 'image__11',
                    'label' => '参考图一',
                    'type' => 'image',
                ],
                [
                    'node' => '12',
                    'field' => 'image',
                    'input_key' => 'image__12',
                    'label' => '参考图二',
                    'type' => 'image',
                ],
            ],
        ]);

        $resolved = app(WorkflowResolver::class)->resolve($workflow, [
            'image__11' => 'first.png',
            'image__12' => 'second.png',
        ]);

        $this->assertSame('first.png', $resolved['11']['inputs']['image']);
        $this->assertSame('second.png', $resolved['12']['inputs']['image']);
    }
}
