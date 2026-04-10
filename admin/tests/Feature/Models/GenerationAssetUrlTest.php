<?php

use App\Models\GenerationAsset;

it('resolves remote comfy asset urls from metadata when no local storage path exists', function () {
    config()->set('services.comfyui.base_url', 'https://comfyui.example.com');

    $asset = new GenerationAsset([
        'filename' => 'sample.png',
        'storage_disk' => 'local',
        'storage_path' => null,
        'metadata_json' => [
            'subfolder' => 'text2img',
            'comfy_type' => 'output',
        ],
    ]);

    expect($asset->resolved_url)->toBe(
        'https://comfyui.example.com/api/view?filename=sample.png&subfolder=text2img&type=output'
    );
});
