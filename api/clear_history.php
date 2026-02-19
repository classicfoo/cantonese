<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/db.php';

$pdo = db_connect($config);
$pdo->exec('DELETE FROM practice_logs');
json_response(['ok' => true]);
