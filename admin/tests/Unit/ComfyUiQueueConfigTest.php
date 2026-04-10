<?php

use App\Jobs\PollComfyUiJobStatus;

it('uses queue work instead of queue listen in the dev script', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true, flags: JSON_THROW_ON_ERROR);
    $devScript = implode(' ', $composer['scripts']['dev'] ?? []);

    expect($devScript)->toContain('php artisan queue:work')
        ->not->toContain('php artisan queue:listen');
});

it('keeps the comfy polling job timeout below the worker default timeout window', function () {
    expect((new PollComfyUiJobStatus(1))->timeout)->toBeLessThanOrEqual(55);
});
