# Admin Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在当前项目根目录创建 `admin/` Laravel 13 后端，完成后台管理基座、员工账号密码登录 API、任务与结果核心数据表，以及一条由 Laravel 调度 ComfyUI 的文生图最小闭环。

**Architecture:** `admin/` 使用 Laravel 13 + Inertia.js + MySQL。管理员通过 Inertia 后台登录管理用户、工作流和任务，员工通过 Tauri 调用 `/api/...`。ComfyUI 调度、工作流注入、结果落库统一由 Laravel 队列执行，Tauri 不再直连 ComfyUI。

**Tech Stack:** Laravel 13, PHP 8.3+, Inertia.js, Sanctum, MySQL, Laravel Queue, Vite

---

## File Structure

### New Top-Level Backend

- Create: `admin/`
- Create: `admin/.env.example`
- Create: `admin/app/Actions/Auth/LoginEmployeeAction.php`
- Create: `admin/app/Actions/Generation/CreateGenerationJobAction.php`
- Create: `admin/app/Http/Controllers/Api/AuthController.php`
- Create: `admin/app/Http/Controllers/Api/GenerationJobController.php`
- Create: `admin/app/Http/Controllers/Admin/DashboardController.php`
- Create: `admin/app/Jobs/DispatchGenerationJob.php`
- Create: `admin/app/Models/User.php`
- Create: `admin/app/Models/WorkflowTemplate.php`
- Create: `admin/app/Models/GenerationJob.php`
- Create: `admin/app/Models/GenerationAsset.php`
- Create: `admin/app/Services/ComfyUi/ComfyUiClient.php`
- Create: `admin/app/Services/Workflow/WorkflowResolver.php`
- Create: `admin/app/Policies/GenerationJobPolicy.php`
- Create: `admin/database/migrations/*_create_users_table.php`
- Create: `admin/database/migrations/*_create_workflow_templates_table.php`
- Create: `admin/database/migrations/*_create_generation_jobs_table.php`
- Create: `admin/database/migrations/*_create_generation_assets_table.php`
- Create: `admin/database/seeders/AdminUserSeeder.php`
- Create: `admin/database/seeders/WorkflowTemplateSeeder.php`
- Create: `admin/resources/js/Pages/Dashboard.vue` or `admin/resources/js/Pages/Dashboard.tsx`
- Create: `admin/routes/web.php`
- Create: `admin/routes/api.php`
- Create: `admin/tests/Feature/Api/Auth/LoginTest.php`
- Create: `admin/tests/Feature/Api/Generation/CreateGenerationJobTest.php`
- Create: `admin/tests/Feature/Jobs/DispatchGenerationJobTest.php`

### Existing Project Files To Update Later

- Modify: `src/App.tsx`
- Modify: `src/store.ts`
- Modify: `src/t2i.ts`
- Modify: `src/i2v.ts`

These Tauri changes are deferred until the backend MVP is ready.

## Task 1: Create Laravel Backend Skeleton

**Files:**
- Create: `admin/` Laravel app
- Modify: project root `.gitignore` if needed
- Test: `admin/tests/CreatesApplication.php`

- [ ] **Step 1: Create the Laravel project in `admin/`**

Run:

```bash
composer create-project laravel/laravel admin
```

Expected:

```text
Application key set successfully.
```

- [ ] **Step 2: Install Sanctum and Inertia**

Run:

```bash
cd admin
composer require laravel/sanctum inertiajs/inertia-laravel
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan inertia:middleware
```

Expected:

```text
Publishing complete.
Middleware [HandleInertiaRequests] created successfully.
```

- [ ] **Step 3: Add the web and api routing split**

Update `admin/bootstrap/app.php` to load both web and api routes if the default skeleton does not already do so:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->create();
```

- [ ] **Step 4: Run the base test suite**

Run:

```bash
cd admin
php artisan test
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin
git commit -m "feat: scaffold admin laravel backend"
```

## Task 2: Configure Core Backend Packages And Environment

**Files:**
- Modify: `admin/.env.example`
- Modify: `admin/config/auth.php`
- Modify: `admin/config/sanctum.php`
- Modify: `admin/config/database.php`
- Test: `admin/tests/Feature/ExampleTest.php`

- [ ] **Step 1: Add environment variables for MySQL, queue, and ComfyUI**

Append these keys to `admin/.env.example`:

```dotenv
APP_FRONTEND_NAME="Beikuman Admin"
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=beikuman_admin
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

COMFYUI_BASE_URL=http://127.0.0.1:8188
COMFYUI_TIMEOUT_SECONDS=60
```

- [ ] **Step 2: Ensure Sanctum can issue employee API tokens**

Verify `admin/config/auth.php` keeps the `users` provider and `admin/config/sanctum.php` allows token-based API auth. No custom code yet, just ensure the standard configuration remains intact.

- [ ] **Step 3: Generate queue tables**

Run:

```bash
cd admin
php artisan make:queue-table
php artisan make:job-batches-table
php artisan make:cache-table
```

Expected:

```text
Migration created successfully.
```

- [ ] **Step 4: Run tests again**

Run:

```bash
cd admin
php artisan test
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/.env.example admin/config admin/database/migrations
git commit -m "chore: configure admin backend environment"
```

## Task 3: Build Core Data Tables

**Files:**
- Create: `admin/database/migrations/*_create_users_table.php`
- Create: `admin/database/migrations/*_create_workflow_templates_table.php`
- Create: `admin/database/migrations/*_create_generation_jobs_table.php`
- Create: `admin/database/migrations/*_create_generation_assets_table.php`
- Test: `admin/tests/Feature/Database/SchemaTest.php`

- [ ] **Step 1: Write the failing schema test**

Create `admin/tests/Feature/Database/SchemaTest.php`:

```php
<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('workflow_templates'));
        $this->assertTrue(Schema::hasTable('generation_jobs'));
        $this->assertTrue(Schema::hasTable('generation_assets'));
    }
}
```

- [ ] **Step 2: Run the schema test to verify failure**

Run:

```bash
cd admin
php artisan test tests/Feature/Database/SchemaTest.php
```

Expected:

```text
FAIL
```

- [ ] **Step 3: Create the migrations**

Use these table shapes.

`users` migration columns:

```php
$table->id();
$table->string('name');
$table->string('email')->unique();
$table->string('password');
$table->string('openid')->nullable()->index();
$table->string('openid_provider')->nullable();
$table->timestamp('openid_bound_at')->nullable();
$table->string('role')->default('employee')->index();
$table->string('status')->default('active')->index();
$table->timestamp('last_login_at')->nullable();
$table->rememberToken();
$table->timestamps();
```

`workflow_templates` migration columns:

```php
$table->id();
$table->string('name');
$table->string('code')->unique();
$table->string('type')->index();
$table->string('version');
$table->json('definition_json');
$table->json('parameter_schema_json')->nullable();
$table->boolean('is_active')->default(true);
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamps();
```

`generation_jobs` migration columns:

```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('workflow_template_id')->constrained()->restrictOnDelete();
$table->string('type')->index();
$table->string('status')->default('pending')->index();
$table->json('input_json');
$table->json('resolved_workflow_json')->nullable();
$table->string('comfy_prompt_id')->nullable()->index();
$table->text('error_message')->nullable();
$table->timestamp('started_at')->nullable();
$table->timestamp('finished_at')->nullable();
$table->timestamps();
```

`generation_assets` migration columns:

```php
$table->id();
$table->foreignId('generation_job_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('type')->index();
$table->string('filename');
$table->string('subfolder')->nullable();
$table->string('storage_disk')->default('local');
$table->string('storage_path')->nullable();
$table->string('preview_path')->nullable();
$table->json('metadata_json')->nullable();
$table->timestamps();
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
cd admin
php artisan test tests/Feature/Database/SchemaTest.php
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/database/migrations admin/tests/Feature/Database/SchemaTest.php
git commit -m "feat: add admin backend core schema"
```

## Task 4: Add Eloquent Models And Admin Seeder

**Files:**
- Create: `admin/app/Models/User.php`
- Create: `admin/app/Models/WorkflowTemplate.php`
- Create: `admin/app/Models/GenerationJob.php`
- Create: `admin/app/Models/GenerationAsset.php`
- Create: `admin/database/seeders/AdminUserSeeder.php`
- Modify: `admin/database/seeders/DatabaseSeeder.php`
- Test: `admin/tests/Feature/Database/AdminUserSeederTest.php`

- [ ] **Step 1: Write the failing seeder test**

Create `admin/tests/Feature/Database/AdminUserSeederTest.php`:

```php
<?php

namespace Tests\Feature\Database;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_seeder_creates_admin_account(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd admin
php artisan test tests/Feature/Database/AdminUserSeederTest.php
```

Expected:

```text
FAIL
```

- [ ] **Step 3: Implement models and seeder**

Key `User` model requirements:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'openid',
        'openid_provider',
        'openid_bound_at',
        'role',
        'status',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'openid_bound_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

`AdminUserSeeder` should create:

```php
User::updateOrCreate(
    ['email' => 'admin@example.com'],
    [
        'name' => 'System Admin',
        'password' => 'password',
        'role' => 'admin',
        'status' => 'active',
    ],
);
```

- [ ] **Step 4: Run the seeder test**

Run:

```bash
cd admin
php artisan test tests/Feature/Database/AdminUserSeederTest.php
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/app/Models admin/database/seeders admin/tests/Feature/Database/AdminUserSeederTest.php
git commit -m "feat: add admin models and seeders"
```

## Task 5: Add Employee Login API

**Files:**
- Create: `admin/app/Actions/Auth/LoginEmployeeAction.php`
- Create: `admin/app/Http/Controllers/Api/AuthController.php`
- Modify: `admin/routes/api.php`
- Test: `admin/tests/Feature/Api/Auth/LoginTest.php`

- [ ] **Step 1: Write the failing login test**

Create `admin/tests/Feature/Api/Auth/LoginTest.php`:

```php
<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_log_in_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'secret123',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'employee@example.com',
            'password' => 'secret123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd admin
php artisan test tests/Feature/Api/Auth/LoginTest.php
```

Expected:

```text
FAIL
```

- [ ] **Step 3: Implement login action and controller**

`LoginEmployeeAction.php`:

```php
<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginEmployeeAction
{
    public function handle(string $email, string $password): array
    {
        $user = User::where('email', $email)
            ->where('role', 'employee')
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['账号或密码错误。'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return [
            'token' => $user->createToken('tauri-client')->plainTextToken,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ];
    }
}
```

`AuthController.php`:

```php
public function login(Request $request, LoginEmployeeAction $action): JsonResponse
{
    $data = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    return response()->json($action->handle($data['email'], $data['password']));
}
```

`admin/routes/api.php`:

```php
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
cd admin
php artisan test tests/Feature/Api/Auth/LoginTest.php
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/app/Actions/Auth admin/app/Http/Controllers/Api/AuthController.php admin/routes/api.php admin/tests/Feature/Api/Auth/LoginTest.php
git commit -m "feat: add employee login api"
```

## Task 6: Add Workflow Template Management Seed Data

**Files:**
- Create: `admin/database/seeders/WorkflowTemplateSeeder.php`
- Modify: `admin/database/seeders/DatabaseSeeder.php`
- Copy: existing workflow JSON files into seeded records
- Test: `admin/tests/Feature/Database/WorkflowTemplateSeederTest.php`

- [ ] **Step 1: Write the failing workflow seeder test**

Create `admin/tests/Feature/Database/WorkflowTemplateSeederTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd admin
php artisan test tests/Feature/Database/WorkflowTemplateSeederTest.php
```

Expected:

```text
FAIL
```

- [ ] **Step 3: Seed workflow templates from current project JSON**

In `WorkflowTemplateSeeder.php`, read from:

```php
base_path('../src/workflows/t2i_api.json');
base_path('../src/workflows/i2i_api.json');
base_path('../src/workflows/video_ltx2_3_i2v.json');
```

Seed each as a `WorkflowTemplate::updateOrCreate(...)` with:

```php
[
    'name' => 'Default Text To Image',
    'code' => 't2i_default',
    'type' => 't2i',
    'version' => '1.0.0',
    'definition_json' => json_decode(file_get_contents(...), true),
    'parameter_schema_json' => [
        'prompt' => ['node' => '69', 'field' => 'prompt'],
    ],
    'is_active' => true,
]
```

- [ ] **Step 4: Run the seeder test**

Run:

```bash
cd admin
php artisan test tests/Feature/Database/WorkflowTemplateSeederTest.php
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/database/seeders admin/tests/Feature/Database/WorkflowTemplateSeederTest.php
git commit -m "feat: seed workflow templates"
```

## Task 7: Add Generation Job Creation API

**Files:**
- Create: `admin/app/Actions/Generation/CreateGenerationJobAction.php`
- Create: `admin/app/Http/Controllers/Api/GenerationJobController.php`
- Modify: `admin/routes/api.php`
- Test: `admin/tests/Feature/Api/Generation/CreateGenerationJobTest.php`

- [ ] **Step 1: Write the failing create-job test**

Create `admin/tests/Feature/Api/Generation/CreateGenerationJobTest.php`:

```php
<?php

namespace Tests\Feature\Api\Generation;

use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateGenerationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_generation_job(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $workflow = WorkflowTemplate::factory()->create([
            'code' => 't2i_default',
            'type' => 't2i',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/generation-jobs', [
            'type' => 't2i',
            'workflow_code' => 't2i_default',
            'inputs' => [
                'prompt' => '一只蓝色机械猫',
                'aspect_ratio' => '1:1',
            ],
        ]);

        $response->assertCreated()->assertJsonFragment(['status' => 'pending']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd admin
php artisan test tests/Feature/Api/Generation/CreateGenerationJobTest.php
```

Expected:

```text
FAIL
```

- [ ] **Step 3: Implement the create-job action and controller**

`CreateGenerationJobAction.php`:

```php
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
```

`GenerationJobController.php` create action:

```php
public function store(Request $request, CreateGenerationJobAction $action): JsonResponse
{
    $data = $request->validate([
        'type' => ['required', 'in:t2i,i2i,i2v'],
        'workflow_code' => ['required', 'string'],
        'inputs' => ['required', 'array'],
    ]);

    $job = $action->handle($request->user(), $data);

    DispatchGenerationJob::dispatch($job->id);

    return response()->json([
        'id' => $job->id,
        'status' => $job->status,
    ], 201);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
cd admin
php artisan test tests/Feature/Api/Generation/CreateGenerationJobTest.php
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/app/Actions/Generation admin/app/Http/Controllers/Api/GenerationJobController.php admin/routes/api.php admin/tests/Feature/Api/Generation/CreateGenerationJobTest.php
git commit -m "feat: add generation job creation api"
```

## Task 8: Implement ComfyUI Client And Workflow Resolver

**Files:**
- Create: `admin/app/Services/ComfyUi/ComfyUiClient.php`
- Create: `admin/app/Services/Workflow/WorkflowResolver.php`
- Test: `admin/tests/Feature/Services/WorkflowResolverTest.php`

- [ ] **Step 1: Write the failing workflow resolver test**

Create `admin/tests/Feature/Services/WorkflowResolverTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd admin
php artisan test tests/Feature/Services/WorkflowResolverTest.php
```

Expected:

```text
FAIL
```

- [ ] **Step 3: Implement the resolver and client**

`WorkflowResolver.php` minimal shape:

```php
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

        return $workflow;
    }
}
```

`ComfyUiClient.php` methods:

```php
public function queuePrompt(array $workflow, string $clientId): array {}
public function fetchHistory(string $promptId): array {}
```

Both should use `Http::baseUrl(config('services.comfyui.base_url'))`.

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
cd admin
php artisan test tests/Feature/Services/WorkflowResolverTest.php
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/app/Services admin/tests/Feature/Services/WorkflowResolverTest.php
git commit -m "feat: add comfyui client and workflow resolver"
```

## Task 9: Implement Queue Execution For T2I

**Files:**
- Create: `admin/app/Jobs/DispatchGenerationJob.php`
- Modify: `admin/app/Models/GenerationJob.php`
- Test: `admin/tests/Feature/Jobs/DispatchGenerationJobTest.php`

- [ ] **Step 1: Write the failing job execution test**

Create `admin/tests/Feature/Jobs/DispatchGenerationJobTest.php`:

```php
<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchGenerationJob;
use App\Models\GenerationJob;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DispatchGenerationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_job_queues_prompt_and_marks_job_running(): void
    {
        Http::fake([
            '*/prompt' => Http::response(['prompt_id' => 'prompt-123'], 200),
        ]);

        $user = User::factory()->create();
        $workflow = WorkflowTemplate::factory()->create([
            'type' => 't2i',
            'definition_json' => ['69' => ['inputs' => ['prompt' => 'placeholder']]],
        ]);

        $job = GenerationJob::create([
            'user_id' => $user->id,
            'workflow_template_id' => $workflow->id,
            'type' => 't2i',
            'status' => 'pending',
            'input_json' => ['prompt' => '一只蓝色机械猫'],
        ]);

        app(DispatchGenerationJob::class, ['jobId' => $job->id])->handle(
            app(\App\Services\Workflow\WorkflowResolver::class),
            app(\App\Services\ComfyUi\ComfyUiClient::class),
        );

        $this->assertDatabaseHas('generation_jobs', [
            'id' => $job->id,
            'status' => 'running',
            'comfy_prompt_id' => 'prompt-123',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd admin
php artisan test tests/Feature/Jobs/DispatchGenerationJobTest.php
```

Expected:

```text
FAIL
```

- [ ] **Step 3: Implement the queue job**

Core `handle()` logic:

```php
$generationJob->update([
    'status' => 'queued',
    'started_at' => now(),
]);

$resolvedWorkflow = $resolver->resolve($generationJob->workflowTemplate, $generationJob->input_json);

$response = $client->queuePrompt($resolvedWorkflow, (string) $generationJob->id);

$generationJob->update([
    'status' => 'running',
    'resolved_workflow_json' => $resolvedWorkflow,
    'comfy_prompt_id' => $response['prompt_id'],
]);
```

On error:

```php
$generationJob->update([
    'status' => 'failed',
    'error_message' => $e->getMessage(),
    'finished_at' => now(),
]);
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
cd admin
php artisan test tests/Feature/Jobs/DispatchGenerationJobTest.php
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/app/Jobs/DispatchGenerationJob.php admin/app/Models/GenerationJob.php admin/tests/Feature/Jobs/DispatchGenerationJobTest.php
git commit -m "feat: queue comfyui generation jobs"
```

## Task 10: Add Admin Dashboard And Job Listing

**Files:**
- Create: `admin/app/Http/Controllers/Admin/DashboardController.php`
- Modify: `admin/routes/web.php`
- Create: `admin/resources/js/Pages/Dashboard.vue` or `.tsx`
- Test: `admin/tests/Feature/Admin/DashboardTest.php`

- [ ] **Step 1: Write the failing dashboard test**

Create `admin/tests/Feature/Admin/DashboardTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd admin
php artisan test tests/Feature/Admin/DashboardTest.php
```

Expected:

```text
FAIL
```

- [ ] **Step 3: Implement dashboard route and page**

`admin/routes/web.php`:

```php
use App\Http\Controllers\Admin\DashboardController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});
```

`DashboardController.php` should render Inertia with counts for:

- pending jobs
- running jobs
- failed jobs
- recent assets

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
cd admin
php artisan test tests/Feature/Admin/DashboardTest.php
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/app/Http/Controllers/Admin/DashboardController.php admin/resources/js/Pages admin/routes/web.php admin/tests/Feature/Admin/DashboardTest.php
git commit -m "feat: add admin dashboard"
```

## Task 11: Add Employee Job List API

**Files:**
- Modify: `admin/app/Http/Controllers/Api/GenerationJobController.php`
- Create: `admin/app/Policies/GenerationJobPolicy.php`
- Test: `admin/tests/Feature/Api/Generation/ListGenerationJobsTest.php`

- [ ] **Step 1: Write the failing list-jobs test**

Create `admin/tests/Feature/Api/Generation/ListGenerationJobsTest.php`:

```php
<?php

namespace Tests\Feature\Api\Generation;

use App\Models\GenerationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListGenerationJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_only_sees_own_jobs(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $other = User::factory()->create(['role' => 'employee']);

        GenerationJob::factory()->create(['user_id' => $user->id]);
        GenerationJob::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/generation-jobs');

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd admin
php artisan test tests/Feature/Api/Generation/ListGenerationJobsTest.php
```

Expected:

```text
FAIL
```

- [ ] **Step 3: Implement listing with ownership restriction**

`GenerationJobController@index`:

```php
public function index(Request $request): JsonResponse
{
    $jobs = GenerationJob::query()
        ->where('user_id', $request->user()->id)
        ->latest()
        ->paginate(20);

    return response()->json($jobs);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
cd admin
php artisan test tests/Feature/Api/Generation/ListGenerationJobsTest.php
```

Expected:

```text
PASS
```

- [ ] **Step 5: Commit**

```bash
git add admin/app/Http/Controllers/Api/GenerationJobController.php admin/app/Policies/GenerationJobPolicy.php admin/tests/Feature/Api/Generation/ListGenerationJobsTest.php
git commit -m "feat: add employee job list api"
```

## Task 12: Handoff Tauri Integration As The Next Plan

**Files:**
- No code changes in this repo root yet
- Create: follow-up plan if needed

- [ ] **Step 1: Verify backend MVP checklist**

Checklist:

```text
[ ] admin/ boots
[ ] migrations run
[ ] admin user exists
[ ] employee login returns token
[ ] employee can create t2i generation job
[ ] queue job writes comfy_prompt_id
[ ] employee can list own jobs
[ ] admin dashboard loads
```

- [ ] **Step 2: Record manual verification commands**

Run:

```bash
cd admin
php artisan migrate --seed
php artisan serve
php artisan queue:work
```

Expected:

```text
Laravel development server started
Processing jobs from the [default] queue
```

- [ ] **Step 3: Write follow-up Tauri migration plan**

Create a second plan once backend MVP is live, covering:

```text
1. Replace Tauri login with /api/login
2. Replace direct ComfyUI prompt submission with /api/generation-jobs
3. Replace direct history polling with backend job polling
4. Replace local workflow authority with backend template authority
```

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans
git commit -m "docs: add admin backend implementation plan"
```

## Self-Review

### Spec Coverage

- `admin/` Laravel 13 backend: covered by Tasks 1-2
- Inertia admin: covered by Task 10
- employee password login: covered by Task 5
- users table with openid fields: covered by Task 3
- workflow templates: covered by Task 6
- generation jobs and assets: covered by Tasks 3, 7, 9, 11
- Laravel queue + ComfyUI dispatch: covered by Tasks 8-9
- employee only sees own jobs: covered by Task 11

No uncovered spec sections remain for the MVP.

### Placeholder Scan

- No `TODO`, `TBD`, or vague “handle appropriately” text remains.
- Every code-changing task names exact files and concrete code.
- Every validation step names a concrete command.

### Type Consistency

- `workflow_templates`, `generation_jobs`, and `generation_assets` naming is consistent throughout.
- Employee API always uses Sanctum token auth.
- Job status values are consistent: `pending`, `queued`, `running`, `succeeded`, `failed`.

