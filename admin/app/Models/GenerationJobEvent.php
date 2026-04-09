<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationJobEvent extends Model
{
    protected $fillable = [
        'generation_job_id',
        'status',
        'progress',
        'message',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'progress' => 'integer',
        ];
    }

    public function generationJob(): BelongsTo
    {
        return $this->belongsTo(GenerationJob::class);
    }
}
