<?php
return [
    'dashscope_api_key' => 'YOUR_DASHSCOPE_API_KEY',
    'dashscope_base_url' => 'https://dashscope-intl.aliyuncs.com',

    // Qwen model defaults; adjust as needed.
    'asr_model' => 'qwen3-asr-flash',
    'chat_model' => 'qwen-plus',
    'tts_model' => 'qwen3-tts-flash',

    // Try yue for Cantonese; if unavailable for your account/region, use zh.
    'asr_language' => 'yue',

    // Voice options depend on your account's available voices.
    'tts_voice' => 'Cherry',
    'tts_volume' => 50,
    'tts_speed' => 1.0,
    'tts_pitch' => 1.0,

    'sqlite_path' => __DIR__ . '/data/app.sqlite',
];
