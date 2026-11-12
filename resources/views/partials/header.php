<?php
declare(strict_types=1);

use App\Support\View;

/** @var string $appName */
/** @var string $basePath */
/** @var string $githubUrl */
/** @var string $activeNav */
$bp = rtrim($basePath ?? '', '/');
$active = $activeNav ?? 'home';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Qubiq — 100% Free, Privacy-First Open-Source QR Code Generator &amp; Developer API. Export PNG, SVG, PDF, EPS, WebP, and JPEG with zero watermarks or registration.">
  <title><?= View::e($pageTitle ?? ($appName . ' — Free Open-Source QR Code Generator')) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= View::e($bp) ?>/favicon.svg">
  <link rel="stylesheet" href="<?= View::e($bp) ?>/assets/css/app.css">
  <script type="module" src="<?= View::e($bp) ?>/assets/js/theme.js"></script>
</head>
<body class="min-h-screen flex flex-col bg-[var(--bg-page)] text-[var(--text-primary)] antialiased selection:bg-emerald-500/20 selection:text-emerald-600 dark:selection:text-emerald-300">
  <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:px-4 focus:py-2 focus:rounded-lg focus:bg-emerald-600 focus:text-white focus:font-semibold">
    Skip to main content
  </a>

  <header class="sticky top-0 z-40 border-b border-[var(--border-subtle)] bg-[var(--bg-surface)]/90 backdrop-blur-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
      <a href="<?= View::e($bp ?: '/') ?>" class="flex items-center gap-3 group focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 rounded-lg">
        <span class="w-9 h-9 rounded-xl bg-slate-900 dark:bg-emerald-950/80 border border-emerald-500/30 flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform" aria-hidden="true">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="3" width="7" height="7" rx="2" stroke="#10b981" stroke-width="2.2"/>
            <rect x="5.3" y="5.3" width="2.4" height="2.4" rx="0.6" fill="#10b981"/>
            <rect x="14" y="3" width="7" height="7" rx="2" stroke="#34d399" stroke-width="2.2"/>
            <rect x="16.3" y="5.3" width="2.4" height="2.4" rx="0.6" fill="#34d399"/>
            <rect x="3" y="14" width="7" height="7" rx="2" stroke="#10b981" stroke-width="2.2"/>
            <rect x="5.3" y="16.3" width="2.4" height="2.4" rx="0.6" fill="#10b981"/>
            <path d="M14 14h3v3h-3v-3Zm4 4h3v3h-3v-3Zm-4 2h2v1h-2v-1Zm5-6h2v3h-2v-3Z" fill="currentColor" class="text-white"/>
          </svg>
        </span>
        <div class="flex flex-col">
          <span class="text-lg font-bold tracking-tight leading-none flex items-center gap-2">
            <?= View::e($appName) ?>
            <span class="text-[11px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">100% Free</span>
          </span>
          <span class="text-xs text-[var(--text-muted)] hidden sm:inline">Open-Source QR Studio</span>
        </div>
      </a>

      <nav class="hidden md:flex items-center gap-1" aria-label="Primary Navigation">
        <a href="<?= View::e($bp ?: '/') ?>" class="nav-pill <?= $active === 'home' ? 'nav-pill-active' : '' ?>">Generator</a>
        <a href="<?= View::e($bp ?: '/') ?>#features" class="nav-pill">Features</a>
        <a href="<?= View::e($bp) ?>/api-docs" class="nav-pill <?= $active === 'api' ? 'nav-pill-active' : '' ?>">Developer API</a>
        <a href="<?= View::e($bp) ?>/docs" class="nav-pill <?= $active === 'docs' ? 'nav-pill-active' : '' ?>">Documentation</a>
      </nav>

      <div class="flex items-center gap-2">
        <button type="button" id="theme-toggle-btn" class="icon-btn" aria-label="Toggle light and dark theme" title="Toggle theme">
          <svg id="theme-icon-sun" class="w-5 h-5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
          </svg>
          <svg id="theme-icon-moon" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
          </svg>
        </button>

        <a href="<?= View::e($githubUrl ?? 'https://github.com/MrSwagger1337/Qubiq') ?>" target="_blank" rel="noopener noreferrer" class="hidden sm:inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border border-[var(--border-subtle)] hover:bg-[var(--bg-muted)] transition-colors">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0 1 12 6.844a9.59 9.59 0 0 1 2.504.337c1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.02 10.02 0 0 0 22 12.017C22 6.484 17.522 2 12 2Z"/></svg>
          <span>GitHub</span>
        </a>

        <button type="button" id="mobile-menu-btn" class="md:hidden icon-btn" aria-expanded="false" aria-controls="mobile-nav-drawer" aria-label="Open navigation menu">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>

    <div id="mobile-nav-drawer" class="hidden md:hidden border-t border-[var(--border-subtle)] bg-[var(--bg-surface)] px-4 py-3 space-y-1">
      <a href="<?= View::e($bp ?: '/') ?>" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-[var(--bg-muted)]">Generator</a>
      <a href="<?= View::e($bp ?: '/') ?>#features" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-[var(--bg-muted)]">Features</a>
      <a href="<?= View::e($bp) ?>/api-docs" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-[var(--bg-muted)]">Developer API</a>
      <a href="<?= View::e($bp) ?>/docs" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-[var(--bg-muted)]">Documentation</a>
      <a href="<?= View::e($githubUrl ?? 'https://github.com/MrSwagger1337/Qubiq') ?>" target="_blank" rel="noopener noreferrer" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-[var(--bg-muted)]">GitHub Repository</a>
    </div>
  </header>

