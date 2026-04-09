<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('openid')->nullable()->index();
            $table->string('openid_provider')->nullable();
            $table->timestamp('openid_bound_at')->nullable();
            $table->string('role')->default('employee')->index();
            $table->string('status')->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'openid',
                'openid_provider',
                'openid_bound_at',
                'role',
                'status',
                'last_login_at',
            ]);
        });
    }
};
