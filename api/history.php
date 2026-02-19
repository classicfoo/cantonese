<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/db.php';

$pdo = db_connect($config);
$rows = $pdo->query(
    'SELECT id, created_at, transcript, reply_cantonese, explanation_english, tts_audio_url
     FROM practice_logs ORDER BY id DESC LIMIT 30'
)->fetchAll(PDO::FETCH_ASSOC);

json_response(['ok' => true, 'items' => $rows]);
