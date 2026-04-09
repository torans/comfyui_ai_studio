<?php

namespace App\Models;

use Database\Factories\GenerationAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationAsset extends Model
{
    /** @use HasFactory<GenerationAssetFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'generation_job_id',
        'user_id',
        'type',
        'filename',
        'subfolder',
        'storage_disk',
        'storage_path',
        'preview_path',
        'metadata_json',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
        ];
    }

    /**
     * Get the generation job that produced this asset.
     */
    public function generationJob(): BelongsTo
    {
        return $this->belongsTo(GenerationJob::class);
    }

    /**
     * Get the user that owns this asset.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get assets by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
