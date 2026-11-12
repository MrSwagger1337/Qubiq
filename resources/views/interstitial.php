<?php
declare(strict_types=1);

use App\Support\View;

$pageTitle = 'Redirect Notice — ' . ($appName ?? 'Qubiq');
$activeNav = '';
require __DIR__ . '/partials/header.php';
?>
<main id="main-content" class="flex-1 flex items-center justify-center px-4 py-16">
  <div class="max-w-lg w-full card-panel p-8 space-y-5">
    <div class="flex items-center gap-3">
      <span class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">!</span>
      <div>
        <h1 class="text-lg font-bold">External Destination Preview</h1>
        <p class="text-xs text-[var(--text-muted)]">Verify the destination address before continuing.</p>
      </div>
    </div>
    <div class="p-3.5 rounded-xl bg-[var(--bg-muted)] border border-[var(--border-subtle)] break-all font-mono text-xs">
      <?= View::e($destinationUrl ?? '') ?>
    </div>
    <div class="flex items-center justify-end gap-3">
      <a href="<?= View::e(($basePath ?? '') ?: '/') ?>" class="btn-secondary">Cancel</a>
      <a href="<?= View::e($destinationUrl ?? '#') ?>" rel="noopener noreferrer nofollow" class="btn-primary">Continue to Website</a>
    </div>
  </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>

