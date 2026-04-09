<?php

namespace Tests\Feature\Api\WorkflowTemplate;

use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 工作流模板 API 测试
 */
class WorkflowTemplateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试管理员可以获取工作流列表
     */
    public function test_admin_can_list_workflows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        WorkflowTemplate::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/workflow-templates');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'code', 'type', 'version', 'is_active'],
            ],
        ]);
    }

    /**
     * 测试可以按类型筛选工作流
     */
    public function test_can_filter_workflows_by_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        WorkflowTemplate::factory()->create(['type' => 't2i']);
        WorkflowTemplate::factory()->create(['type' => 'i2v']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/workflow-templates?type=t2i');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('t2i', $response->json('data.0.type'));
    }

    /**
     * 测试可以启动工作流
     */
    public function test_can_start_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = WorkflowTemplate::factory()->create(['is_active' => false]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/workflow-templates/{$workflow->id}/start");

        $response->assertOk();
        $this->assertDatabaseHas('workflow_templates', [
            'id' => $workflow->id,
            'is_active' => true,
        ]);
    }

    /**
     * 测试可以停止工作流
     */
    public function test_can_stop_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = WorkflowTemplate::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/workflow-templates/{$workflow->id}/stop");

        $response->assertOk();
        $this->assertDatabaseHas('workflow_templates', [
            'id' => $workflow->id,
            'is_active' => false,
        ]);
    }

    /**
     * 测试员工也可以获取工作流列表
     */
    public function test_employee_can_list_workflows(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        WorkflowTemplate::factory()->count(2)->create(['is_active' => true]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/workflow-templates');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
