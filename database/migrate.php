<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var \App\Support\DatabaseManager $db */
$db = $app['db'];
$db->migrate();

fwrite(STDOUT, "Qubiq SQLite migrations applied successfully.\n");
