<?php
declare(strict_types=1);

use App\Support\View;

$pageTitle = 'Manage Dynamic QR — ' . ($appName ?? 'Qubiq');
$activeNav = '';
require __DIR__ . '/partials/header.php';
$bp = rtrim($basePath ?? '', '/');

$manageBootJson = json_encode([
    'basePath' => $bp,
    'baseUrl' => $baseUrl ?? '',
    'shortCode' => $shortCode ?? '',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
?>
<main id="main-content" class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <span class="badge-pill">Anonymous Dynamic QR Management</span>
      <h1 class="text-2xl font-extrabold mt-2">Short Link <span class="font-mono text-emerald-600 dark:text-emerald-400">/r/<?= View::e($shortCode ?? '') ?></span></h1>
      <p class="text-xs text-[var(--text-muted)] mt-1">
        Authenticated via secret ownership token in your browser fragment (never sent in URL query strings).
      </p>
    </div>
    <a href="<?= View::e($bp ?: '/') ?>" class="btn-secondary text-xs">← Back to Generator</a>
  </div>

  <!-- Token Prompt (shown if URL hash #token=... is not present) -->
  <section id="manage-auth-card" class="card-panel p-6 space-y-4">
    <h2 class="font-bold text-base">Enter Ownership Secret</h2>
    <p class="text-xs text-[var(--text-muted)]">
      Paste your <code class="font-mono">qubiq_own_...</code> secret token to unlock editing and scan analytics for this QR code.
    </p>
    <form id="manage-auth-form" class="flex flex-col sm:flex-row gap-3">
      <label for="manage-token-input" class="sr-only">Ownership Token</label>
      <input type="password" id="manage-token-input" placeholder="qubiq_own_..." required class="form-input font-mono text-sm flex-1">
      <button type="submit" class="btn-primary text-sm">Unlock Dashboard</button>
    </form>
    <div id="manage-auth-error" class="hidden text-xs text-rose-600 dark:text-rose-400 font-medium" role="alert"></div>
  </section>

  <!-- Authenticated Management Dashboard -->
  <div id="manage-dashboard" class="hidden space-y-6">
    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="card-panel p-4">
        <span class="text-xs text-[var(--text-muted)]">Total Scans</span>
        <div id="stat-total" class="text-2xl font-extrabold mt-1">0</div>
      </div>
      <div class="card-panel p-4">
        <span class="text-xs text-[var(--text-muted)]">Today (UTC)</span>
        <div id="stat-today" class="text-2xl font-extrabold mt-1">0</div>
      </div>
      <div class="card-panel p-4">
        <span class="text-xs text-[var(--text-muted)]">Last 7 Days</span>
        <div id="stat-week" class="text-2xl font-extrabold mt-1">0</div>
      </div>
      <div class="card-panel p-4">
        <span class="text-xs text-[var(--text-muted)]">Last 30 Days</span>
        <div id="stat-month" class="text-2xl font-extrabold mt-1">0</div>
      </div>
    </div>

    <!-- Edit Destination & Status -->
    <section class="card-panel p-6 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold text-base">Destination &amp; Redirect Status</h2>
        <span id="manage-status-pill" class="badge-pill">Active</span>
      </div>

      <form id="manage-update-form" class="space-y-4">
        <div>
          <label for="manage-dest-input" class="field-label">Target Destination URL (HTTP / HTTPS)</label>
          <input type="url" id="manage-dest-input" required class="form-input font-mono text-sm">
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
          <label class="inline-flex items-center gap-2 text-sm font-medium cursor-pointer">
            <input type="checkbox" id="manage-active-checkbox" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            <span>Redirect is active (uncheck to temporarily pause scans)</span>
          </label>
          <button type="submit" class="btn-primary text-sm">Save Changes</button>
        </div>
      </form>

      <div class="pt-3 border-t border-[var(--border-subtle)] grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-[var(--text-muted)]">
        <div>Short URL: <a id="manage-short-link" href="#" target="_blank" rel="noopener noreferrer" class="font-mono text-emerald-600 dark:text-emerald-400 underline"></a></div>
        <div>Created: <span id="manage-created-at" class="font-mono"></span></div>
        <div>Last Scan: <span id="manage-last-scan" class="font-mono">Never</span></div>
      </div>
    </section>

    <!-- Aggregated Privacy-Respecting Analytics -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <section class="card-panel p-6 space-y-3">
        <h3 class="font-bold text-sm">Daily Scans (Last 30 Days)</h3>
        <div id="daily-chart-list" class="space-y-2 text-xs"></div>
      </section>

      <section class="card-panel p-6 space-y-3">
        <h3 class="font-bold text-sm">Device Categories (Coarse, Zero-Fingerprint)</h3>
        <div id="device-breakdown-list" class="space-y-2 text-xs"></div>
      </section>
    </div>

    <!-- Security Controls: Token Rotation & Permanent Deletion -->
    <section class="card-panel p-6 border-rose-500/30 space-y-4">
      <h2 class="font-bold text-base">Security &amp; Ownership Controls</h2>
      <p class="text-xs text-[var(--text-muted)]">
        Suspect your management link was exposed? Rotate your ownership token immediately to invalidate the previous secret, or permanently delete this dynamic QR link and all aggregated scan counters.
      </p>
      <div class="flex flex-wrap items-center gap-3">
        <button type="button" id="rotate-token-btn" class="btn-secondary text-xs">Rotate Ownership Token</button>
        <button type="button" id="delete-link-btn" class="btn-secondary text-rose-600 dark:text-rose-400 text-xs">Delete Dynamic QR Permanently</button>
      </div>
      <div id="rotated-token-box" class="hidden p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs space-y-2">
        <div class="font-bold text-amber-700 dark:text-amber-300">New Ownership Token Generated — Copy &amp; Bookmark Now:</div>
        <input type="text" id="rotated-token-value" readonly class="form-input font-mono text-xs">
      </div>
    </section>
  </div>
</main>

<script id="qubiq-manage-boot" type="application/json"><?= $manageBootJson ?></script>
<script type="module" src="<?= View::e($bp) ?>/assets/js/manage.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>

