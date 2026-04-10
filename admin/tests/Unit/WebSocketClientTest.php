<?php

use App\Services\ComfyUi\WebSocketClient;

it('clears the socket reference when closing the websocket client', function () {
    $client = new WebSocketClient('https://example.com');
    $socket = fopen('php://temp', 'r+');

    $reflection = new ReflectionClass($client);
    $socketProperty = $reflection->getProperty('socket');
    $socketProperty->setAccessible(true);
    $socketProperty->setValue($client, $socket);

    $client->close();

    expect($socketProperty->getValue($client))->toBeNull();
});
