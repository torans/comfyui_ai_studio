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
