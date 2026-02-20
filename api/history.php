<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/db.php';

$pdo = db_connect($config);
$rows = $pdo->query(
    'SELECT id, created_at, transcript, transcript_yale, corrected_cantonese, corrected_cantonese_yale, reply_cantonese, reply_cantonese_yale, explanation_english, tts_audio_url
     FROM practice_logs ORDER BY id DESC LIMIT 30'
)->fetchAll(PDO::FETCH_ASSOC);

json_response(['ok' => true, 'items' => $rows]);
