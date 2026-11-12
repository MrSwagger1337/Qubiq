<?php
declare(strict_types=1);

use App\Support\View;

$pageTitle = ($statusCode ?? 404) . ' — ' . ($appName ?? 'Qubiq');
$activeNav = '';
require __DIR__ . '/partials/header.php';
$bp = rtrim($basePath ?? '', '/');
?>
<main id="main-content" class="flex-1 flex items-center justify-center px-4 py-16">
  <div class="max-w-md w-full card-panel p-8 text-center space-y-4">
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold text-xl">
      <?= View::e($statusCode ?? 404) ?>
    </div>
    <h1 class="text-xl font-bold"><?= View::e($title ?? 'Error') ?></h1>
    <p class="text-sm text-[var(--text-muted)]"><?= View::e($message ?? 'The requested resource could not be found.') ?></p>
    <div class="pt-2">
      <a href="<?= View::e($bp ?: '/') ?>" class="btn-primary inline-flex items-center gap-2">
        Return to QR Generator
      </a>
    </div>
  </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>

