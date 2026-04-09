<?php

namespace Tests\Feature\Database;

use App\Models\WorkflowTemplate;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_templates_are_seeded(): void
    {
        $this->seed(WorkflowTemplateSeeder::class);

        $this->assertDatabaseHas('workflow_templates', [
            'code' => 't2i_default',
            'type' => 't2i',
        ]);
    }
}
