<?php

declare(strict_types=1);

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['APP_URL'] = 'https://qubiq.test';
$_ENV['DB_DATABASE'] = ':memory:';
$_ENV['LOGGING_ENABLED'] = 'false';
$_ENV['LOG_CHANNEL'] = 'null';
$_ENV['RATE_LIMIT_ENABLED'] = 'false';

require_once dirname(__DIR__) . '/vendor/autoload.php';
