<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Missing config.php. Copy config.example.php to config.php and set your API key.',
    ]);
    exit;
}

$config = require $configFile;

function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '{}';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function post_json(string $url, array $payload, string $apiKey): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 90,
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'status' => 0, 'error' => $err ?: 'curl_exec failed'];
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        $data = ['raw' => $resp];
    }

    return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'data' => $data];
}

function extract_message_text(array $message): string
{
    $content = $message['content'] ?? '';
    if (is_string($content)) {
        return trim($content);
    }

    if (!is_array($content)) {
        return '';
    }

    $chunks = [];
    foreach ($content as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (isset($item['text']) && is_string($item['text'])) {
            $chunks[] = $item['text'];
        }
        if (isset($item['content']) && is_string($item['content'])) {
            $chunks[] = $item['content'];
        }
    }

    return trim(implode("\n", $chunks));
}
