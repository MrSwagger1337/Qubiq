<?php
declare(strict_types=1);

use App\Support\View;

$pageTitle = 'Documentation — ' . ($appName ?? 'Qubiq');
$activeNav = 'docs';
require __DIR__ . '/partials/header.php';
$bp = rtrim($basePath ?? '', '/');
?>
<main id="main-content" class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
  <div class="space-y-2">
    <span class="badge-pill">User &amp; Self-Hosting Guide</span>
    <h1 class="text-3xl font-extrabold tracking-tight"><?= View::e($appName ?? 'Qubiq') ?> Documentation</h1>
    <p class="text-sm text-[var(--text-muted)] max-w-3xl">
      Everything you need to generate scannable QR codes, understand export formats, manage anonymous dynamic redirects, and self-host on PHP 8.2+ shared hosting, VPS, or Docker.
    </p>
  </div>

  <!-- Static vs Dynamic -->
  <section class="card-panel p-6 space-y-3">
    <h2 class="text-lg font-bold">1. Static vs. Anonymous Dynamic QR Codes</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs leading-relaxed text-[var(--text-muted)]">
      <div class="p-4 rounded-xl bg-[var(--bg-muted)]/60 border border-[var(--border-subtle)] space-y-1.5">
        <h3 class="font-bold text-sm text-[var(--text-primary)]">Static QR Codes (Default)</h3>
        <p>Encodes your destination URL, Wi-Fi credentials, vCard, or text directly into the QR modules. Zero database storage, zero reliance on this server remaining online, and infinite lifetime. Static QR codes cannot be edited once printed.</p>
      </div>
      <div class="p-4 rounded-xl bg-[var(--bg-muted)]/60 border border-[var(--border-subtle)] space-y-1.5">
        <h3 class="font-bold text-sm text-[var(--text-primary)]">Optional Dynamic QR Codes</h3>
        <p>Encodes a short <code class="font-mono">/r/{code}</code> URL hosted on this instance and issues a one-time 256-bit ownership secret (<code class="font-mono">qubiq_own_...</code>). Only the SHA-256 hash is stored in SQLite. You can update the destination URL, rotate the secret, or view privacy-preserving daily scan totals without an account.</p>
      </div>
    </div>
  </section>

  <!-- Export Pipeline Capabilities -->
  <section class="card-panel p-6 space-y-3">
    <h2 class="text-lg font-bold">2. Export Formats &amp; Renderer Fidelity</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-[var(--border-subtle)] text-[var(--text-muted)]">
            <th class="py-2 pr-4">Format</th>
            <th class="py-2 pr-4">Engine</th>
            <th class="py-2 pr-4">Transparency</th>
            <th class="py-2">Styling &amp; Print Notes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border-subtle)]">
          <tr><td class="py-2.5 pr-4 font-bold">SVG</td><td class="py-2.5 pr-4">Native XML Vector</td><td class="py-2.5 pr-4">Yes</td><td class="py-2.5">Infinite resolution, true SVG <code class="font-mono">&lt;linearGradient&gt;</code> / <code class="font-mono">&lt;radialGradient&gt;</code>, embedded PNG logo.</td></tr>
          <tr><td class="py-2.5 pr-4 font-bold">PNG</td><td class="py-2.5 pr-4">2× Supersampled GD</td><td class="py-2.5 pr-4">Yes (RGBA)</td><td class="py-2.5">Crisp anti-aliased edges up to 2048×2048 px. Best general-purpose raster export.</td></tr>
          <tr><td class="py-2.5 pr-4 font-bold">PDF</td><td class="py-2.5 pr-4">Native FPDF Bézier Vector</td><td class="py-2.5 pr-4">Page-native</td><td class="py-2.5">Print-ready vector paths with per-module gradient interpolation and embedded PNG logo.</td></tr>
          <tr><td class="py-2.5 pr-4 font-bold">EPS</td><td class="py-2.5 pr-4">PostScript Level 2 Vector</td><td class="py-2.5 pr-4">Yes (No BG rect)</td><td class="py-2.5">Editable vector curves for Illustrator, Affinity, and prepress workflows; logos use PS Level 2 RGB <code class="font-mono">colorimage</code>.</td></tr>
          <tr><td class="py-2.5 pr-4 font-bold">WebP</td><td class="py-2.5 pr-4">2× Supersampled GD</td><td class="py-2.5 pr-4">Yes</td><td class="py-2.5">Compact modern web raster format with alpha channel support.</td></tr>
          <tr><td class="py-2.5 pr-4 font-bold">JPEG</td><td class="py-2.5 pr-4">2× Supersampled GD</td><td class="py-2.5 pr-4">Opaque only</td><td class="py-2.5">Transparent backgrounds automatically composite onto clean white (<code class="font-mono">#ffffff</code>).</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Shared Hosting & Deployment -->
  <section class="card-panel p-6 space-y-3">
    <h2 class="text-lg font-bold">3. Deploying on PHP 8.2+ Shared Hosting (Zero Node.js Required)</h2>
    <p class="text-xs text-[var(--text-muted)] leading-relaxed">
      All production CSS and ES Module JavaScript assets are pre-built and committed directly inside <code class="font-mono">public/assets/</code>. <strong>Node.js is never required on your web server.</strong>
    </p>
    <ol class="list-decimal list-inside text-xs space-y-1.5 text-[var(--text-muted)]">
      <li>Ensure PHP <strong>8.2, 8.3, or 8.4+</strong> is selected in cPanel / DirectAdmin / Plesk with <code class="font-mono">gd</code>, <code class="font-mono">mbstring</code>, <code class="font-mono">fileinfo</code>, and <code class="font-mono">pdo_sqlite</code> (optional, for dynamic links) enabled.</li>
      <li>Run <code class="font-mono">composer install --no-dev --optimize-autoloader</code> locally or via cPanel Terminal, and copy <code class="font-mono">.env.example</code> to <code class="font-mono">.env</code>.</li>
      <li><strong>Recommended:</strong> Point your domain or subdomain Document Root to the <code class="font-mono">/public</code> folder.</li>
      <li><strong>Locked <code class="font-mono">public_html</code> fallback:</strong> If your shared host cannot change Document Root, upload the project into <code class="font-mono">public_html/</code> — the included hardened root <code class="font-mono">.htaccess</code> and directory <code class="font-mono">Require all denied</code> guards automatically block direct HTTP access to <code class="font-mono">.env</code>, <code class="font-mono">storage/</code>, <code class="font-mono">vendor/</code>, and <code class="font-mono">app/</code>.</li>
    </ol>
  </section>

  <!-- Supported Content Types -->
  <section class="card-panel p-6 space-y-3">
    <h2 class="text-lg font-bold">4. All 21 Supported Content Types</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
      <?php foreach (($typesCatalog ?? []) as $item): ?>
        <div class="p-3 rounded-xl bg-[var(--bg-muted)]/50 border border-[var(--border-subtle)] space-y-1">
          <div class="flex items-center justify-between">
            <span class="font-bold text-[var(--text-primary)]"><?= View::e($item['label']) ?></span>
            <span class="font-mono text-[10px] opacity-70"><?= View::e($item['type']) ?></span>
          </div>
          <p class="text-[var(--text-muted)]"><?= View::e($item['description']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>

