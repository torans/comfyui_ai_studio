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
        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->string('thumb')->nullable()->after('name');
            $table->text('description')->nullable()->after('thumb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->dropColumn(['thumb', 'description']);
        });
    }
};
