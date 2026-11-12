<?php
declare(strict_types=1);

use App\Support\View;

$bp = rtrim($basePath ?? '', '/');
?>
  <footer class="mt-auto border-t border-[var(--border-subtle)] bg-[var(--bg-surface)] py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-[var(--text-muted)]">
      <div class="flex items-center gap-2">
        <span class="font-semibold text-[var(--text-primary)]"><?= View::e($appName ?? 'Qubiq') ?></span>
        <span>•</span>
        <span>100% Free, Stateless Static QR Codes &amp; Privacy-First Open Source (MIT License)</span>
      </div>
      <div class="flex flex-wrap items-center gap-4">
        <a href="<?= View::e($bp ?: '/') ?>" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">Studio</a>
        <a href="<?= View::e($bp) ?>/api-docs" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">REST API v1</a>
        <a href="<?= View::e($bp) ?>/openapi.json" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">OpenAPI 3.1</a>
        <a href="<?= View::e($bp) ?>/docs" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">Docs &amp; Security</a>
      </div>
    </div>
  </footer>

  <div id="toast-region" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none px-4 sm:px-0" role="status" aria-live="polite"></div>
</body>
</html>

