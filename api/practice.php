<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/db.php';

function contains_han(string $text): bool
{
    return preg_match('/\p{Han}/u', $text) === 1;
}

function generate_yale_romanization(
    string $text,
    string $baseUrl,
    string $apiKey,
    string $chatModel
): string {
    if ($text === '' || !contains_han($text)) {
        return '';
    }

    $payload = [
        'model' => $chatModel,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Convert Cantonese Traditional Chinese text to Yale romanization. '
                    . 'Return STRICT JSON: {"yale":"..."} only. Keep punctuation and sentence order.',
            ],
            ['role' => 'user', 'content' => $text],
        ],
        'stream' => false,
        'temperature' => 0.1,
    ];

    $resp = post_json($baseUrl . '/compatible-mode/v1/chat/completions', $payload, $apiKey);
    if (!$resp['ok']) {
        return '';
    }

    $message = $resp['data']['choices'][0]['message'] ?? [];
    $textOut = extract_message_text(is_array($message) ? $message : []);
    if ($textOut === '') {
        return '';
    }
    $parsed = json_decode($textOut, true);
    if (!is_array($parsed)) {
        return '';
    }
    return trim((string) ($parsed['yale'] ?? ''));
}

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

$systemPrompt = 'You are a Cantonese speaking coach. Always answer in spoken Hong Kong Cantonese (粵語口語), '
    . 'using Traditional Chinese characters and natural particles like 啦、喎、㗎、呀. '
    . 'Avoid Mandarin wording such as 是、不、什么、你们、我们、他们. '
    . 'Prefer Cantonese wording such as 係、唔、乜嘢、你哋、我哋、佢哋. '
    . 'Keep the Cantonese reply short and conversational (1-3 sentences). '
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
    'temperature' => 0.5,
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

$chatModel = (string) ($config['chat_model'] ?? 'qwen-plus');
$transcriptYale = generate_yale_romanization($transcript, $baseUrl, $apiKey, $chatModel);
$replyCantoneseYale = generate_yale_romanization($replyCantonese, $baseUrl, $apiKey, $chatModel);

$ttsModel = (string) ($config['tts_model'] ?? 'qwen3-tts-flash');
$ttsPayload = [
    'model' => $ttsModel,
    'input' => [
        'text' => $replyCantonese,
        'voice' => (string) ($config['tts_voice'] ?? 'Kiki'),
        'language_type' => (string) ($config['tts_language_type'] ?? 'Chinese'),
    ],
    'parameters' => [
        'volume' => (int) ($config['tts_volume'] ?? 50),
        'speed' => (float) ($config['tts_speed'] ?? 1.0),
        'pitch' => (float) ($config['tts_pitch'] ?? 1.0),
    ],
];

if (str_contains($ttsModel, 'instruct')) {
    $ttsPayload['parameters']['instructions'] = (string) (
        $config['tts_instructions']
        ?? 'Use natural Hong Kong Cantonese pronunciation and colloquial rhythm. Do not use Mandarin pronunciation.'
    );
    $ttsPayload['parameters']['optimize_instructions'] = true;
}

$ttsResp = post_json($baseUrl . '/api/v1/services/aigc/multimodal-generation/generation', $ttsPayload, $apiKey);
if (!$ttsResp['ok']) {
    json_response([
        'ok' => false,
        'error' => 'TTS request failed',
        'status' => $ttsResp['status'],
        'details' => $ttsResp['data'],
        'transcript' => $transcript,
        'transcript_yale' => $transcriptYale,
        'reply_cantonese' => $replyCantonese,
        'reply_cantonese_yale' => $replyCantoneseYale,
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
    'INSERT INTO practice_logs (
        created_at,
        transcript,
        transcript_yale,
        reply_cantonese,
        reply_cantonese_yale,
        explanation_english,
        tts_audio_url
    ) VALUES (
        :created_at,
        :transcript,
        :transcript_yale,
        :reply_cantonese,
        :reply_cantonese_yale,
        :explanation_english,
        :tts_audio_url
    )'
);
$stmt->execute([
    ':created_at' => gmdate('Y-m-d H:i:s'),
    ':transcript' => $transcript,
    ':transcript_yale' => $transcriptYale,
    ':reply_cantonese' => $replyCantonese,
    ':reply_cantonese_yale' => $replyCantoneseYale,
    ':explanation_english' => $explainEnglish,
    ':tts_audio_url' => $audioUrl,
]);

json_response([
    'ok' => true,
    'transcript' => $transcript,
    'transcript_yale' => $transcriptYale,
    'reply_cantonese' => $replyCantonese,
    'reply_cantonese_yale' => $replyCantoneseYale,
    'explanation_english' => $explainEnglish,
    'audio_url' => $audioUrl,
]);
