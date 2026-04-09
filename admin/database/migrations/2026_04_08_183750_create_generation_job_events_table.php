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
        Schema::create('generation_job_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_job_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // queued, running, progress, completed, failed
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('message')->nullable(); // 状态描述
            $table->json('payload_json')->nullable(); // 额外数据
            $table->timestamps();

            $table->index(['generation_job_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generation_job_events');
    }
};
