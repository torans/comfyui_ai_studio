<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建管理员用户
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_view_employee_list()
    {
        // 创建一些员工
        User::factory()->count(3)->create(['role' => 'employee', 'status' => 'active']);
        User::factory()->count(2)->create(['role' => 'employee', 'status' => 'inactive']);

        $response = $this->actingAs($this->admin)
            ->get('/admin/employees');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('admin/employees/index')
                ->has('employees.data', 5)
        );
    }

    public function test_admin_can_search_employees()
    {
        User::factory()->create([
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'role' => 'employee',
        ]);
        User::factory()->create([
            'name' => '李四',
            'email' => 'lisi@example.com',
            'role' => 'employee',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/employees?search=张');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('admin/employees/index')
                ->has('employees.data', 1)
        );
    }

    public function test_admin_can_filter_by_status()
    {
        User::factory()->count(2)->create(['role' => 'employee', 'status' => 'active']);
        User::factory()->count(3)->create(['role' => 'employee', 'status' => 'inactive']);

        $response = $this->actingAs($this->admin)
            ->get('/admin/employees?status=active');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('admin/employees/index')
                ->has('employees.data', 2)
        );
    }

    public function test_admin_can_view_create_page()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/employees/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('admin/employees/create')
        );
    }

    public function test_admin_can_create_employee()
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/employees', [
                'name' => '新员工',
                'email' => 'new@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'status' => 'active',
            ]);

        $response->assertRedirect('/admin/employees');
        $response->assertSessionHas('success', '员工创建成功');

        $this->assertDatabaseHas('users', [
            'name' => '新员工',
            'email' => 'new@example.com',
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_view_edit_page()
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAs($this->admin)
            ->get("/admin/employees/{$employee->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('admin/employees/edit')
                ->where('employee.id', $employee->id)
        );
    }

    public function test_admin_can_update_employee()
    {
        $employee = User::factory()->create([
            'name' => '旧名字',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/admin/employees/{$employee->id}", [
                'name' => '新名字',
                'status' => 'inactive',
            ]);

        $response->assertRedirect('/admin/employees');
        $response->assertSessionHas('success', '员工信息更新成功');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => '新名字',
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_update_employee_password()
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAs($this->admin)
            ->put("/admin/employees/{$employee->id}", [
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertRedirect('/admin/employees');

        // 验证密码已更新
        $employee->refresh();
        $this->assertTrue(password_verify('newpassword123', $employee->password));
    }

    public function test_admin_can_disable_employee()
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/admin/employees/{$employee->id}");

        $response->assertRedirect('/admin/employees');
        $response->assertSessionHas('success', '员工已禁用');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_cannot_edit_admin_user()
    {
        $admin2 = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)
            ->get("/admin/employees/{$admin2->id}/edit");

        $response->assertStatus(404);
    }

    public function test_admin_cannot_update_admin_user()
    {
        $admin2 = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)
            ->put("/admin/employees/{$admin2->id}", [
                'name' => '尝试修改',
            ]);

        $response->assertStatus(404);
    }
}
