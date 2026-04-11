<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

/**
 * 员工管理控制器
 */
class EmployeeController extends Controller
{
    /**
     * 员工列表页面
     */
    public function index(Request $request): Response
    {
        $query = User::where('role', 'employee');

        // 搜索过滤
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 状态过滤
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $employees = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/employees/index', [
            'employees' => $employees,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * 新增员工页面
     */
    public function create(): Response
    {
        return Inertia::render('admin/employees/create');
    }

    /**
     * 保存新员工
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'employee';

        User::create($data);

        return redirect()->route('admin.employees.index')->with('success', '员工创建成功');
    }

    /**
     * 编辑员工页面
     */
    public function edit(User $employee): Response
    {
        // 确保只能编辑员工
        if ($employee->role !== 'employee') {
            abort(404);
        }

        return Inertia::render('admin/employees/edit', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'status' => $employee->status,
                'last_login_at' => $employee->last_login_at?->toDateTimeString(),
                'created_at' => $employee->created_at->toDateTimeString(),
            ],
        ]);
    }

    /**
     * 更新员工信息
     */
    public function update(Request $request, User $employee): RedirectResponse
    {
        // 确保只能编辑员工
        if ($employee->role !== 'employee') {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->id)],
            'status' => ['sometimes', 'in:active,inactive'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        // 如果有密码，需要加密
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $employee->update($data);

        return redirect()->route('admin.employees.index')->with('success', '员工信息更新成功');
    }

    /**
     * 删除员工（软删除或禁用）
     */
    public function destroy(User $employee): RedirectResponse
    {
        // 确保只能删除员工
        if ($employee->role !== 'employee') {
            abort(404);
        }

        // 使用禁用而不是删除
        $employee->update(['status' => 'inactive']);

        return redirect()->route('admin.employees.index')->with('success', '员工已禁用');
    }
}
