<?php

declare(strict_types=1);

function db_connect(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = $config['sqlite_path'] ?? (__DIR__ . '/../data/app.sqlite');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS practice_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            transcript TEXT NOT NULL,
            transcript_yale TEXT,
            corrected_cantonese TEXT,
            corrected_cantonese_yale TEXT,
            reply_cantonese TEXT NOT NULL,
            reply_cantonese_yale TEXT,
            explanation_english TEXT,
            tts_audio_url TEXT
        )'
    );

    ensure_column($pdo, 'practice_logs', 'transcript_yale', 'TEXT');
    ensure_column($pdo, 'practice_logs', 'corrected_cantonese', 'TEXT');
    ensure_column($pdo, 'practice_logs', 'corrected_cantonese_yale', 'TEXT');
    ensure_column($pdo, 'practice_logs', 'reply_cantonese_yale', 'TEXT');

    return $pdo;
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }
    $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
}
