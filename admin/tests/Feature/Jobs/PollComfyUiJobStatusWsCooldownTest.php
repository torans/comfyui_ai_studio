<?php

use App\Jobs\PollComfyUiJobStatus;
use Illuminate\Support\Facades\Cache;

it('disables websocket retries for a job during the cooldown window', function () {
    Cache::flush();

    $job = new PollComfyUiJobStatus(99);

    $disableMethod = new ReflectionMethod($job, 'temporarilyDisableWebSocket');
    $disableMethod->setAccessible(true);
    $disableMethod->invoke($job, 120, 'timeout');

    expect(Cache::has('poll_ws_disabled_99'))->toBeTrue();

    $shouldUseMethod = new ReflectionMethod($job, 'shouldUseWebSocket');
    $shouldUseMethod->setAccessible(true);

    expect($shouldUseMethod->invoke($job))->toBeFalse();
});
