<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/db.php';

$apiKey = trim((string) ($config['dashscope_api_key'] ?? ''));
if ($apiKey === '') {
    json_response(['ok' => false, 'error' => 'dashscope_api_key is empty in config.php'], 500);
}

$input = json_input();
$audioData = (string) ($input['audio_data'] ?? '');
$withEnglish = (bool) ($input['with_english'] ?? true);

if ($audioData === '' || !str_contains($audioData, 'base64,')) {
    json_response(['ok' => false, 'error' => 'Invalid audio_data; expected data URI'], 422);
}

$baseUrl = rtrim((string) ($config['dashscope_base_url'] ?? 'https://dashscope-intl.aliyuncs.com'), '/');

$asrPayload = [
    'model' => (string) ($config['asr_model'] ?? 'qwen3-asr-flash'),
    'messages' => [[
        'role' => 'user',
        'content' => [[
            'type' => 'input_audio',
            'input_audio' => [
                'data' => $audioData,
            ],
        ]],
    ]],
    'stream' => false,
    'asr_options' => [
        'language' => (string) ($config['asr_language'] ?? 'yue'),
        'enable_itn' => false,
    ],
];

$asrResp = post_json($baseUrl . '/compatible-mode/v1/chat/completions', $asrPayload, $apiKey);
if (!$asrResp['ok']) {
    json_response([
        'ok' => false,
        'error' => 'ASR request failed',
        'status' => $asrResp['status'],
        'details' => $asrResp['data'],
    ], 502);
}

$asrMessage = $asrResp['data']['choices'][0]['message'] ?? [];
$transcript = extract_message_text(is_array($asrMessage) ? $asrMessage : []);
if ($transcript === '') {
    json_response([
        'ok' => false,
        'error' => 'ASR returned empty transcript',
        'details' => $asrResp['data'],
    ], 502);
}

$systemPrompt = 'You are a Cantonese speaking coach. Reply in Traditional Chinese Cantonese style, natural and short. '
    . 'Return STRICT JSON only with keys: cantonese_reply, english_explanation. '
    . 'If english explanation is not requested, english_explanation should be an empty string.';

$userInstruction = $withEnglish
    ? 'User wants a quick English explanation too.'
    : 'Do not provide English explanation.';

$chatPayload = [
    'model' => (string) ($config['chat_model'] ?? 'qwen-plus'),
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userInstruction . "\n\nLearner said (Cantonese): " . $transcript],
    ],
    'stream' => false,
    'temperature' => 0.6,
];

$chatResp = post_json($baseUrl . '/compatible-mode/v1/chat/completions', $chatPayload, $apiKey);
if (!$chatResp['ok']) {
    json_response([
        'ok' => false,
        'error' => 'Chat request failed',
        'status' => $chatResp['status'],
        'details' => $chatResp['data'],
    ], 502);
}

$chatMessage = $chatResp['data']['choices'][0]['message'] ?? [];
$chatText = extract_message_text(is_array($chatMessage) ? $chatMessage : []);

$replyCantonese = '';
$explainEnglish = '';
$parsed = json_decode($chatText, true);
if (is_array($parsed)) {
    $replyCantonese = trim((string) ($parsed['cantonese_reply'] ?? ''));
    $explainEnglish = trim((string) ($parsed['english_explanation'] ?? ''));
}

if ($replyCantonese === '') {
    $replyCantonese = $chatText;
}
if (!$withEnglish) {
    $explainEnglish = '';
}

$ttsPayload = [
    'model' => (string) ($config['tts_model'] ?? 'qwen3-tts-flash'),
    'input' => [
        'text' => $replyCantonese,
    ],
    'parameters' => [
        'voice' => (string) ($config['tts_voice'] ?? 'Cherry'),
        'volume' => (int) ($config['tts_volume'] ?? 50),
        'speed' => (float) ($config['tts_speed'] ?? 1.0),
        'pitch' => (float) ($config['tts_pitch'] ?? 1.0),
    ],
];

$ttsResp = post_json($baseUrl . '/api/v1/services/aigc/multimodal-generation/generation', $ttsPayload, $apiKey);
if (!$ttsResp['ok']) {
    json_response([
        'ok' => false,
        'error' => 'TTS request failed',
        'status' => $ttsResp['status'],
        'details' => $ttsResp['data'],
        'transcript' => $transcript,
        'reply_cantonese' => $replyCantonese,
        'explanation_english' => $explainEnglish,
    ], 502);
}

$ttsData = $ttsResp['data'];
$audioUrl = '';
if (isset($ttsData['output']['audio']['url']) && is_string($ttsData['output']['audio']['url'])) {
    $audioUrl = $ttsData['output']['audio']['url'];
}
if ($audioUrl === '' && isset($ttsData['output']['audio_url']) && is_string($ttsData['output']['audio_url'])) {
    $audioUrl = $ttsData['output']['audio_url'];
}

$pdo = db_connect($config);
$stmt = $pdo->prepare(
    'INSERT INTO practice_logs (created_at, transcript, reply_cantonese, explanation_english, tts_audio_url)
     VALUES (:created_at, :transcript, :reply_cantonese, :explanation_english, :tts_audio_url)'
);
$stmt->execute([
    ':created_at' => gmdate('Y-m-d H:i:s'),
    ':transcript' => $transcript,
    ':reply_cantonese' => $replyCantonese,
    ':explanation_english' => $explainEnglish,
    ':tts_audio_url' => $audioUrl,
]);

json_response([
    'ok' => true,
    'transcript' => $transcript,
    'reply_cantonese' => $replyCantonese,
    'explanation_english' => $explainEnglish,
    'audio_url' => $audioUrl,
]);
