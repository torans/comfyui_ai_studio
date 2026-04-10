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

    public function getResolvedUrlAttribute(): ?string
    {
        $remoteUrl = $this->metadata_json['remote_url'] ?? null;
        if ($remoteUrl) {
            return $remoteUrl;
        }

        if ($this->storage_path) {
            return app('filesystem')->disk($this->storage_disk)->url($this->storage_path);
        }

        if (!$this->filename) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.comfyui.base_url'), '/');
        if ($baseUrl === '') {
            return null;
        }

        $params = http_build_query([
            'filename' => $this->filename,
            'subfolder' => $this->metadata_json['subfolder'] ?? '',
            'type' => $this->metadata_json['comfy_type'] ?? 'output',
        ]);

        return "{$baseUrl}/view?{$params}";
    }

    public function getMediaKindAttribute(): string
    {
        $kind = $this->metadata_json['media_kind'] ?? null;
        if (is_string($kind) && $kind !== '') {
            return $kind;
        }

        $filename = strtolower((string) $this->filename);

        if (str_ends_with($filename, '.mp4') || str_ends_with($filename, '.webm') || str_ends_with($filename, '.mov')) {
            return 'video';
        }

        return 'image';
    }
}
