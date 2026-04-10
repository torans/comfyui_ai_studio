<?php

namespace App\Services\ComfyUi;

use Illuminate\Support\Facades\Log;

class WebSocketClient
{
    private $socket;
    private $baseUrl;
    private string $buffer = '';

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * 建立连接并完成握手
     */
    public function connect(): bool
    {
        $url = parse_url($this->baseUrl);
        $scheme = $url['scheme'] ?? 'http';
        $host = $url['host'];
        $port = $url['port'] ?? ($scheme === 'https' ? 443 : 80);
        
        // 构建 WebSocket URL
        $wsScheme = $scheme === 'https' ? 'wss' : 'ws';
        $wsUrl = "{$wsScheme}://{$host}:{$port}/ws";
        
        Log::info("尝试连接 WebSocket: {$wsUrl}");

        $errno = $errstr = null;
        $context = stream_context_create();
        
        // 构建连接字符串
        $transport = ($wsScheme === 'wss') ? 'ssl' : 'tcp';
        $connectString = "{$transport}://{$host}:{$port}";
        
        // 如果是 SSL，需要 SSL 上下文
        if ($transport === 'ssl') {
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
        }
        
        $this->socket = stream_socket_client(
            $connectString,
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            Log::error("WebSocket 连接失败: {$errstr} ({$errno})");
            return false;
        }

        // 发送握手请求
        $key = base64_encode(random_bytes(16));
        $header = "GET /ws HTTP/1.1\r\n" .
                  "Host: {$host}:{$port}\r\n" .
                  "Upgrade: websocket\r\n" .
                  "Connection: Upgrade\r\n" .
                  "Sec-WebSocket-Key: {$key}\r\n" .
                  "Sec-WebSocket-Version: 13\r\n" .
                  "\r\n";

        fwrite($this->socket, $header);
        
        // 读取响应
        $response = '';
        while (!feof($this->socket)) {
            $chunk = fread($this->socket, 1024);
            if ($chunk === false) break;
            $response .= $chunk;
            if (str_contains($response, "\r\n\r\n")) break;
        }

        if (!str_contains($response, "101 Switching Protocols")) {
            Log::error("WebSocket 握手失败，响应: " . substr($response, 0, 200));
            fclose($this->socket);
            $this->socket = null;
            return false;
        }

        stream_set_blocking($this->socket, false);
        Log::info("WebSocket 连接成功");
        return true;
    }

    /**
     * 读取下一条消息
     */
    public function receive()
    {
        if (!$this->socket || !is_resource($this->socket) || feof($this->socket)) {
            return null;
        }

        $read = [$this->socket];
        $write = [];
        $except = [];

        if (@stream_select($read, $write, $except, 0, 200000) !== 1) {
            return null;
        }

        $chunk = fread($this->socket, 8192);
        if ($chunk === false || $chunk === '') {
            return null;
        }

        $this->buffer .= $chunk;

        return $this->extractFrame();
    }

    public function close()
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }

        $this->buffer = '';
    }

    private function extractFrame(): ?array
    {
        if (strlen($this->buffer) < 2) {
            return null;
        }

        $firstByte = ord($this->buffer[0]);
        $secondByte = ord($this->buffer[1]);
        $opcode = $firstByte & 0x0F;
        $isMasked = ($secondByte & 0x80) !== 0;
        $payloadLength = $secondByte & 0x7F;
        $offset = 2;

        if ($payloadLength === 126) {
            if (strlen($this->buffer) < $offset + 2) {
                return null;
            }

            $payloadLength = unpack('n', substr($this->buffer, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLength === 127) {
            if (strlen($this->buffer) < $offset + 8) {
                return null;
            }

            $lengthBytes = substr($this->buffer, $offset, 8);
            $parts = unpack('N2', $lengthBytes);
            $payloadLength = ($parts[1] << 32) | $parts[2];
            $offset += 8;
        }

        $mask = '';
        if ($isMasked) {
            if (strlen($this->buffer) < $offset + 4) {
                return null;
            }

            $mask = substr($this->buffer, $offset, 4);
            $offset += 4;
        }

        if (strlen($this->buffer) < $offset + $payloadLength) {
            return null;
        }

        $payload = substr($this->buffer, $offset, $payloadLength);
        $this->buffer = substr($this->buffer, $offset + $payloadLength);

        if ($isMasked) {
            $payload = $this->unmask($payload, $mask);
        }

        // 关闭帧直接收口，避免上层继续读失效资源。
        if ($opcode === 0x8) {
            $this->close();
            return null;
        }

        if ($opcode !== 0x1) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function unmask(string $payload, string $mask): string
    {
        $output = '';
        $length = strlen($payload);

        for ($i = 0; $i < $length; $i++) {
            $output .= $payload[$i] ^ $mask[$i % 4];
        }

        return $output;
    }
}
