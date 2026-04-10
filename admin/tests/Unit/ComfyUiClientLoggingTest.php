<?php

it('imports the laravel log facade for retry logging', function () {
    $source = file_get_contents(dirname(__DIR__, 2) . '/app/Services/ComfyUi/ComfyUiClient.php');

    expect($source)->toContain('use Illuminate\\Support\\Facades\\Log;');
});
