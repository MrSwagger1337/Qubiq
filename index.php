<?php

declare(strict_types=1);

/**
 * Shared-hosting entrypoint fallback when DocumentRoot cannot be pointed directly to /public.
 */
require __DIR__ . '/public/index.php';
