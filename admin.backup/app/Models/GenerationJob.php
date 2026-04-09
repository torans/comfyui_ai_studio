<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workflow_template_id',
        'type',
        'status',
        'input_json',
        'resolved_workflow_json',
        'comfy_prompt_id',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'input_json' => 'array',
            'resolved_workflow_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workflowTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    public function generationAssets(): HasMany
    {
        return $this->hasMany(GenerationAsset::class);
    }
}
