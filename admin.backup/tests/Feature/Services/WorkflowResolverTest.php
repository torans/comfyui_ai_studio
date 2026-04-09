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
}
