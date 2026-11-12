<?php
declare(strict_types=1);

use App\Support\View;

$pageTitle = ($appName ?? 'Qubiq') . ' — Free Open-Source QR Code Generator';
$activeNav = 'home';
require __DIR__ . '/partials/header.php';
$bp = rtrim($basePath ?? '', '/');

$primaryTypes = array_values(array_filter(
    $typesCatalog ?? [],
    static fn (array $item): bool => ($item['category'] ?? 'more') === 'primary'
));
$moreTypes = array_values(array_filter(
    $typesCatalog ?? [],
    static fn (array $item): bool => ($item['category'] ?? 'more') !== 'primary'
));

$bootJson = json_encode([
    'basePath' => $bp,
    'baseUrl' => $baseUrl ?? '',
    'dynamicEnabled' => (bool) ($dynamicEnabled ?? false),
    'types' => $typesCatalog ?? [],
    'i18n' => $i18n ?? [],
    'limits' => $limits ?? [],
    'initialMeta' => $initialMeta ?? [],
    'initialPayload' => $initialPayload ?? '',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
?>
<main id="main-content" class="flex-1">
  <!-- Hero Strip -->
  <section class="border-b border-[var(--border-subtle)] bg-gradient-to-b from-emerald-500/[0.06] to-transparent py-6 sm:py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
          Professional QR Code Studio — <span class="text-emerald-600 dark:text-emerald-400">100% Free Forever</span>
        </h1>
        <p class="mt-1 text-sm text-[var(--text-muted)] max-w-2xl">
          No sign-ups, no watermarks, no scan limits. Stateless static QR codes by default, vector print exports (SVG, PDF, EPS), and optional token-protected dynamic links.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="badge-pill">21 Content Types</span>
        <span class="badge-pill">PNG · SVG · PDF · EPS · WebP · JPEG</span>
        <span class="badge-pill">Zero Tracking</span>
      </div>
    </div>
  </section>

  <!-- Content Type Navigation Bar -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6" aria-label="QR Content Types">
    <div class="card-panel p-2 flex flex-wrap items-center gap-1.5 relative">
      <div id="primary-type-tabs" class="flex flex-wrap items-center gap-1 flex-1" role="tablist" aria-label="Primary QR Code Types">
        <?php foreach ($primaryTypes as $idx => $t): ?>
          <button
            type="button"
            role="tab"
            data-qr-type="<?= View::e($t['type']) ?>"
            aria-selected="<?= $idx === 0 ? 'true' : 'false' ?>"
            class="type-tab <?= $idx === 0 ? 'type-tab-active' : '' ?>"
          >
            <span><?= View::e($t['label']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Searchable More Menu -->
      <div class="relative">
        <button
          type="button"
          id="more-types-btn"
          class="type-tab flex items-center gap-1.5 font-semibold"
          aria-expanded="false"
          aria-controls="more-types-popover"
        >
          <span id="more-types-label">More Types (<?= count($moreTypes) ?>)</span>
          <svg class="w-4 h-4 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div
          id="more-types-popover"
          class="hidden absolute right-0 mt-2 w-72 sm:w-80 rounded-2xl border border-[var(--border-subtle)] bg-[var(--bg-surface)] shadow-xl p-3 z-30"
          role="dialog"
          aria-label="Additional QR Code Types"
        >
          <label for="more-types-search" class="sr-only">Search QR types</label>
          <input
            type="search"
            id="more-types-search"
            placeholder="Search 12 more types (Event, Bitcoin, Social...)"
            class="form-input text-xs mb-2"
            autocomplete="off"
          >
          <div id="more-types-list" class="max-h-64 overflow-y-auto grid grid-cols-2 gap-1">
            <?php foreach ($moreTypes as $t): ?>
              <button
                type="button"
                data-qr-type="<?= View::e($t['type']) ?>"
                data-type-label="<?= View::e(strtolower($t['label'])) ?>"
                class="more-type-item text-left px-2.5 py-2 rounded-lg text-xs font-medium hover:bg-emerald-500/10 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
              >
                <?= View::e($t['label']) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Two-Column Generator Studio -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

      <!-- LEFT PANEL: Configuration Accordions -->
      <div class="lg:col-span-7 xl:col-span-8 space-y-4">

        <!-- 1. ENTER CONTENT -->
        <div class="accordion-card" data-accordion="content">
          <button type="button" class="accordion-header" aria-expanded="true" aria-controls="panel-content">
            <span class="flex items-center gap-3">
              <span class="step-badge">1</span>
              <span class="font-bold text-base">Enter Content</span>
              <span id="active-type-pill" class="text-xs font-medium px-2 py-0.5 rounded-md bg-[var(--bg-muted)] text-[var(--text-muted)]">URL</span>
            </span>
            <svg class="accordion-chevron w-5 h-5 transition-transform rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </button>

          <div id="panel-content" class="accordion-body space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-[var(--text-muted)] bg-[var(--bg-muted)]/70 px-3 py-2 rounded-xl border border-[var(--border-subtle)]">
              <p id="type-description-text" class="flex-1">Encodes a standard HTTP or HTTPS website link directly into the QR code.</p>
              <div class="flex items-center gap-2">
                <span id="url-wrapper-badge" class="px-2 py-0.5 rounded bg-sky-500/10 text-sky-600 dark:text-sky-400 font-medium">Standard Web Link</span>
                <button type="button" id="fill-sample-btn" class="text-emerald-600 dark:text-emerald-400 hover:underline font-semibold">Load Sample</button>
              </div>
            </div>

            <!-- Dynamic Form Mount Point -->
            <div id="dynamic-form-fields" class="space-y-3"></div>

            <!-- Inline Field Validation Message -->
            <div id="form-validation-box" class="hidden p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-medium" role="alert"></div>

            <!-- Static vs Optional Dynamic QR Mode -->
            <div class="pt-2 border-t border-[var(--border-subtle)] space-y-3">
              <div class="flex items-start gap-2.5 text-xs text-[var(--text-muted)]">
                <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <p><strong>Stateless Static Mode (Default):</strong> Your content is encoded directly inside the QR matrix. Nothing is saved to any database and the QR code never expires — static codes cannot be edited after downloading.</p>
              </div>

              <?php if (!empty($dynamicEnabled)): ?>
                <div id="dynamic-qr-box" class="p-3.5 rounded-xl border border-[var(--border-subtle)] bg-[var(--bg-muted)]/50 space-y-3">
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <span class="text-sm font-semibold flex items-center gap-2">
                        Statistics &amp; Anonymous Editability (Optional Dynamic QR)
                      </span>
                      <p class="text-xs text-[var(--text-muted)] mt-0.5">
                        Create a short redirect URL with a secret one-time ownership token — edit destination anytime &amp; view privacy-safe scan stats with zero accounts.
                      </p>
                    </div>
                    <button type="button" id="create-dynamic-btn" class="btn-secondary text-xs shrink-0">
                      Create Dynamic Link
                    </button>
                  </div>

                  <!-- Active Dynamic Link Credentials Banner -->
                  <div id="dynamic-credentials-panel" class="hidden p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 space-y-2.5 text-xs">
                    <div class="flex items-center justify-between gap-2">
                      <span class="font-bold text-emerald-700 dark:text-emerald-300">Dynamic QR Active — Save Your Ownership Secret Now!</span>
                      <button type="button" id="exit-dynamic-btn" class="text-rose-600 dark:text-rose-400 hover:underline font-medium">Switch back to Static</button>
                    </div>
                    <p class="text-[var(--text-muted)]">
                      The QR preview now encodes your short redirect link. <strong>Store the ownership token safely</strong> — we only store its SHA-256 hash, so losing it means losing edit access. Also note dynamic links require this server to remain online.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      <div>
                        <label class="block text-[11px] font-semibold uppercase opacity-75 mb-1" for="dyn-short-url-display">Short Redirect URL (in QR)</label>
                        <input id="dyn-short-url-display" type="text" readonly class="form-input text-xs font-mono">
                      </div>
                      <div>
                        <label class="block text-[11px] font-semibold uppercase opacity-75 mb-1" for="dyn-owner-token-display">Secret Ownership Token</label>
                        <div class="flex gap-1.5">
                          <input id="dyn-owner-token-display" type="text" readonly class="form-input text-xs font-mono flex-1">
                          <button type="button" id="copy-dyn-token-btn" class="btn-secondary px-2.5 py-1 text-xs">Copy</button>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                      <a id="open-dyn-manage-link" href="#" target="_blank" rel="noopener noreferrer" class="btn-primary text-xs py-1.5 px-3">
                        Open Anonymous Management &amp; Analytics
                      </a>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- 2. SET COLORS -->
        <div class="accordion-card" data-accordion="colors">
          <button type="button" class="accordion-header" aria-expanded="false" aria-controls="panel-colors">
            <span class="flex items-center gap-3">
              <span class="step-badge">2</span>
              <span class="font-bold text-base">Set Colors &amp; Gradients</span>
            </span>
            <svg class="accordion-chevron w-5 h-5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </button>

          <div id="panel-colors" class="accordion-body hidden space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex items-center gap-2" role="radiogroup" aria-label="Foreground Color Mode">
                <label class="choice-chip"><input type="radio" name="foreground_mode" value="solid" class="sr-only"><span>Solid Color</span></label>
                <label class="choice-chip"><input type="radio" name="foreground_mode" value="linear" checked class="sr-only"><span>Linear Gradient</span></label>
                <label class="choice-chip"><input type="radio" name="foreground_mode" value="radial" class="sr-only"><span>Radial Gradient</span></label>
              </div>
              <button type="button" id="random-palette-btn" class="btn-secondary text-xs py-1.5 px-3">
                Random Scan-Safe Palette
              </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="fg-color-hex" class="field-label">Foreground Primary Color</label>
                <div class="color-field-group">
                  <input type="color" id="fg-color-picker" value="#0f172a" aria-label="Foreground primary color picker" class="color-swatch-input">
                  <input type="text" id="fg-color-hex" value="#0f172a" maxlength="7" class="form-input font-mono text-sm">
                </div>
              </div>

              <div id="fg-gradient-end-wrap">
                <label for="fg-grad-hex" class="field-label">Gradient Second Color</label>
                <div class="color-field-group">
                  <input type="color" id="fg-grad-picker" value="#047857" aria-label="Foreground gradient end color picker" class="color-swatch-input">
                  <input type="text" id="fg-grad-hex" value="#047857" maxlength="7" class="form-input font-mono text-sm">
                </div>
              </div>
            </div>

            <div id="fg-gradient-angle-wrap">
              <div class="flex justify-between text-xs mb-1">
                <label for="gradient-angle" class="font-medium">Gradient Direction Angle</label>
                <span id="gradient-angle-val" class="font-mono text-[var(--text-muted)]">135°</span>
              </div>
              <input type="range" id="gradient-angle" min="0" max="360" value="135" class="w-full accent-emerald-600">
            </div>

            <!-- Custom Eye Colors -->
            <div class="pt-3 border-t border-[var(--border-subtle)] space-y-3">
              <label class="inline-flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input type="checkbox" id="custom-eye-colors-toggle" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>Use custom corner finder (eye) colors</span>
              </label>

              <div id="custom-eye-colors-grid" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label for="finder-outer-hex" class="field-label">Finder Outer Frame Color</label>
                  <div class="color-field-group">
                    <input type="color" id="finder-outer-picker" value="#0f172a" aria-label="Finder outer frame color picker" class="color-swatch-input">
                    <input type="text" id="finder-outer-hex" value="#0f172a" maxlength="7" class="form-input font-mono text-sm">
                  </div>
                </div>
                <div>
                  <label for="finder-inner-hex" class="field-label">Finder Inner Dot Color</label>
                  <div class="color-field-group">
                    <input type="color" id="finder-inner-picker" value="#059669" aria-label="Finder inner dot color picker" class="color-swatch-input">
                    <input type="text" id="finder-inner-hex" value="#059669" maxlength="7" class="form-input font-mono text-sm">
                  </div>
                </div>
              </div>
            </div>

            <!-- Background Options -->
            <div class="pt-3 border-t border-[var(--border-subtle)] space-y-3">
              <span class="block text-sm font-semibold">Background Styling</span>
              <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Background Mode">
                <label class="choice-chip"><input type="radio" name="background_mode" value="solid" checked class="sr-only"><span>Solid</span></label>
                <label class="choice-chip"><input type="radio" name="background_mode" value="transparent" class="sr-only"><span>Transparent</span></label>
                <label class="choice-chip"><input type="radio" name="background_mode" value="linear" class="sr-only"><span>Linear Gradient</span></label>
                <label class="choice-chip"><input type="radio" name="background_mode" value="radial" class="sr-only"><span>Radial Gradient</span></label>
              </div>

              <div id="bg-colors-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label for="bg-color-hex" class="field-label">Background Color</label>
                  <div class="color-field-group">
                    <input type="color" id="bg-color-picker" value="#ffffff" aria-label="Background color picker" class="color-swatch-input">
                    <input type="text" id="bg-color-hex" value="#ffffff" maxlength="7" class="form-input font-mono text-sm">
                  </div>
                </div>
                <div id="bg-grad-end-wrap" class="hidden">
                  <label for="bg-grad-hex" class="field-label">Background Gradient End</label>
                  <div class="color-field-group">
                    <input type="color" id="bg-grad-picker" value="#f1f5f9" aria-label="Background gradient end picker" class="color-swatch-input">
                    <input type="text" id="bg-grad-hex" value="#f1f5f9" maxlength="7" class="form-input font-mono text-sm">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. ADD LOGO IMAGE -->
        <div class="accordion-card" data-accordion="logo">
          <button type="button" class="accordion-header" aria-expanded="false" aria-controls="panel-logo">
            <span class="flex items-center gap-3">
              <span class="step-badge">3</span>
              <span class="font-bold text-base">Add Logo Image</span>
            </span>
            <svg class="accordion-chevron w-5 h-5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </button>

          <div id="panel-logo" class="accordion-body hidden space-y-4">
            <div class="p-4 rounded-xl border-2 border-dashed border-[var(--border-subtle)] bg-[var(--bg-muted)]/40 text-center space-y-2">
              <input type="file" id="logo-file-input" accept="image/png,image/jpeg,image/webp" class="sr-only">
              <div class="flex flex-wrap items-center justify-center gap-2">
                <label for="logo-file-input" class="btn-secondary cursor-pointer text-xs">
                  Upload Logo (PNG, JPEG, WebP)
                </label>
                <button type="button" id="remove-logo-btn" class="hidden btn-secondary text-rose-600 dark:text-rose-400 text-xs">
                  Remove Logo
                </button>
              </div>
              <p id="logo-status-text" class="text-xs text-[var(--text-muted)]">
                Max <?= View::e($limits['logo_max_kb'] ?? 2048) ?> KB. Validated server-side &amp; stripped of metadata. SVG uploads are rejected for security and print export parity.
              </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <label for="logo-scale" class="font-medium">Logo Size Ratio</label>
                  <span id="logo-scale-val" class="font-mono text-[var(--text-muted)]">20%</span>
                </div>
                <input type="range" id="logo-scale" min="10" max="28" value="20" class="w-full accent-emerald-600">
              </div>
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <label for="logo-padding" class="font-medium">Logo Background Padding</label>
                  <span id="logo-padding-val" class="font-mono text-[var(--text-muted)]">8 px</span>
                </div>
                <input type="range" id="logo-padding" min="0" max="28" value="8" class="w-full accent-emerald-600">
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-[var(--border-subtle)]">
              <label class="inline-flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input type="checkbox" id="logo-bg-enabled" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>Draw clean backing plate behind logo</span>
              </label>

              <div class="flex items-center gap-2">
                <label for="logo-bg-shape" class="text-xs font-medium">Plate Shape:</label>
                <select id="logo-bg-shape" class="form-input py-1.5 text-xs w-28">
                  <option value="rounded" selected>Rounded</option>
                  <option value="square">Square</option>
                  <option value="circle">Circle</option>
                </select>
                <input type="color" id="logo-bg-color" value="#ffffff" aria-label="Logo backing plate color" class="color-swatch-input">
              </div>
            </div>
          </div>
        </div>

        <!-- 4. CUSTOMIZE DESIGN & PRESETS -->
        <div class="accordion-card" data-accordion="customize">
          <button type="button" class="accordion-header" aria-expanded="false" aria-controls="panel-customize">
            <span class="flex items-center gap-3">
              <span class="step-badge">4</span>
              <span class="font-bold text-base">Customize Shapes &amp; Design Presets</span>
            </span>
            <svg class="accordion-chevron w-5 h-5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </button>

          <div id="panel-customize" class="accordion-body hidden space-y-5">
            <!-- Quick Built-in Presets -->
            <div>
              <span class="field-label">Scan-Conscious Design Presets</span>
              <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2 mt-1.5" id="builtin-presets-grid">
                <button type="button" data-preset="classic" class="preset-btn">Classic</button>
                <button type="button" data-preset="minimal" class="preset-btn">Minimal</button>
                <button type="button" data-preset="rounded" class="preset-btn preset-btn-active">Rounded</button>
                <button type="button" data-preset="corporate" class="preset-btn">Corporate</button>
                <button type="button" data-preset="gradient" class="preset-btn">Modern</button>
                <button type="button" data-preset="dark" class="preset-btn">Dark Slate</button>
                <button type="button" data-preset="colorful" class="preset-btn">Vibrant</button>
              </div>
            </div>

            <!-- Body Module Shape -->
            <div>
              <span class="field-label">Body Module Shape</span>
              <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mt-1.5" role="radiogroup" aria-label="Body Module Shape">
                <?php
                $moduleShapes = [
                    'square' => 'Square',
                    'rounded' => 'Rounded',
                    'circle' => 'Circle',
                    'dots' => 'Dots',
                    'diamond' => 'Diamond',
                    'soft' => 'Soft',
                ];
foreach ($moduleShapes as $val => $lbl): ?>
                  <label class="shape-card">
                    <input type="radio" name="module_shape" value="<?= View::e($val) ?>" <?= $val === 'rounded' ? 'checked' : '' ?> class="sr-only">
                    <span class="text-xs font-medium"><?= View::e($lbl) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Finder Outer Frame Shape -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <span class="field-label">Corner Frame Shape (Outer)</span>
                <div class="grid grid-cols-2 gap-2 mt-1.5" role="radiogroup" aria-label="Finder Outer Frame Shape">
                  <?php foreach (['square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circular', 'cushion' => 'Cushion'] as $val => $lbl): ?>
                    <label class="shape-card">
                      <input type="radio" name="finder_outer_shape" value="<?= View::e($val) ?>" <?= $val === 'rounded' ? 'checked' : '' ?> class="sr-only">
                      <span class="text-xs font-medium"><?= View::e($lbl) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div>
                <span class="field-label">Corner Eye Shape (Inner)</span>
                <div class="grid grid-cols-3 gap-2 mt-1.5" role="radiogroup" aria-label="Finder Inner Eye Shape">
                  <?php foreach (['square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circle'] as $val => $lbl): ?>
                    <label class="shape-card">
                      <input type="radio" name="finder_inner_shape" value="<?= View::e($val) ?>" <?= $val === 'rounded' ? 'checked' : '' ?> class="sr-only">
                      <span class="text-xs font-medium"><?= View::e($lbl) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 5. ADVANCED QR SETTINGS & JSON IMPORT/EXPORT -->
        <div class="accordion-card" data-accordion="advanced">
          <button type="button" class="accordion-header" aria-expanded="false" aria-controls="panel-advanced">
            <span class="flex items-center gap-3">
              <span class="step-badge">5</span>
              <span class="font-bold text-base">Advanced Settings &amp; Saved Presets</span>
            </span>
            <svg class="accordion-chevron w-5 h-5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </button>

          <div id="panel-advanced" class="accordion-body hidden space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label for="ecc-select" class="field-label" title="Higher levels recover more damaged/covered modules">Error Correction</label>
                <select id="ecc-select" class="form-input text-sm">
                  <option value="L">L — ~7% recovery</option>
                  <option value="M" selected>M — ~15% (Default)</option>
                  <option value="Q">Q — ~25% recovery</option>
                  <option value="H">H — ~30% (Best for Logos)</option>
                </select>
              </div>

              <div>
                <label for="version-select" class="field-label" title="Symbol grid size: Version 1 (21x21) to Version 40 (177x177)">QR Version</label>
                <select id="version-select" class="form-input text-sm">
                  <option value="auto" selected>Auto (Recommended)</option>
                  <?php for ($v = 1; $v <= 40; $v++): ?>
                    <option value="<?= $v ?>">Version <?= $v ?> (<?= ($v * 4) + 17 ?>×<?= ($v * 4) + 17 ?>)</option>
                  <?php endfor; ?>
                </select>
              </div>

              <div>
                <label for="mask-select" class="field-label" title="ISO/IEC 18004 data mask pattern">Mask Pattern</label>
                <select id="mask-select" class="form-input text-sm">
                  <option value="auto" selected>Auto Penalty Optimal</option>
                  <?php for ($m = 0; $m <= 7; $m++): ?>
                    <option value="<?= $m ?>">Mask Pattern <?= $m ?></option>
                  <?php endfor; ?>
                </select>
              </div>

              <div>
                <label for="quiet-zone-input" class="field-label" title="Clear margin around the QR matrix (ISO recommends 4 modules)">Quiet Zone (Modules)</label>
                <input type="number" id="quiet-zone-input" min="0" max="12" value="4" class="form-input text-sm">
              </div>
            </div>

            <p class="text-xs text-[var(--text-muted)]">
              <?= View::e($i18n['notices']['ecc_info'] ?? '') ?>
            </p>

            <!-- Local Saved Presets & JSON Config -->
            <div class="pt-3 border-t border-[var(--border-subtle)] space-y-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-semibold">Saved Local Design Presets &amp; JSON Config</span>
                <div class="flex flex-wrap items-center gap-2">
                  <button type="button" id="save-local-preset-btn" class="btn-secondary text-xs py-1.5 px-3">Save Current Design</button>
                  <button type="button" id="export-json-btn" class="btn-secondary text-xs py-1.5 px-3">Export JSON</button>
                  <label for="import-json-input" class="btn-secondary text-xs py-1.5 px-3 cursor-pointer">Import JSON</label>
                  <input type="file" id="import-json-input" accept="application/json,.json" class="sr-only">
                  <button type="button" id="reset-design-btn" class="btn-secondary text-rose-600 dark:text-rose-400 text-xs py-1.5 px-3">Reset All</button>
                </div>
              </div>
              <div id="saved-presets-list" class="flex flex-wrap gap-2 text-xs"></div>
            </div>
          </div>
        </div>

      </div>

      <!-- RIGHT PANEL: Sticky Live Preview & Export -->
      <aside class="lg:col-span-5 xl:col-span-4 lg:sticky lg:top-20 space-y-4" aria-label="QR Code Preview and Downloads">
        <div class="card-panel p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="font-bold text-sm uppercase tracking-wider text-[var(--text-muted)]">Live Server Preview</h2>
            <span id="preview-status-badge" class="text-xs font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              Ready
            </span>
          </div>

          <!-- White / Checkerboard Canvas -->
          <div id="qr-preview-frame" class="relative aspect-square w-full max-w-[320px] mx-auto rounded-2xl bg-white border border-slate-200 dark:border-slate-700 p-4 flex items-center justify-center shadow-inner overflow-hidden">
            <div id="qr-preview-stage" class="w-full h-full flex items-center justify-center [&>svg]:w-full [&>svg]:h-full">
              <?= $initialSvg ?? '' ?>
            </div>

            <div id="qr-preview-spinner" class="hidden absolute inset-0 bg-white/75 dark:bg-slate-900/75 backdrop-blur-[2px] flex items-center justify-center" aria-hidden="true">
              <div class="w-9 h-9 rounded-full border-3 border-emerald-500 border-t-transparent animate-spin"></div>
            </div>
          </div>

          <!-- Readability & Scan Warnings -->
          <div id="scan-warnings-container" class="hidden space-y-1.5" role="region" aria-label="Scan Readability Warnings"></div>

          <!-- Output Resolution & Filename Controls -->
          <div class="space-y-3 pt-1">
            <div>
              <div class="flex items-center justify-between text-xs mb-1">
                <label for="size-preset-select" class="font-semibold">Output Resolution</label>
                <span id="size-readout" class="font-mono text-[var(--text-muted)]">1000 × 1000 px</span>
              </div>
              <div class="grid grid-cols-3 gap-2">
                <select id="size-preset-select" class="form-input text-xs col-span-2">
                  <option value="256">256 × 256 px (Web Icon)</option>
                  <option value="512">512 × 512 px (Standard)</option>
                  <option value="1000" selected>1000 × 1000 px (High Quality)</option>
                  <option value="1500">1500 × 1500 px (Print Ready)</option>
                  <option value="2048">2048 × 2048 px (Ultra HD)</option>
                  <option value="custom">Custom Dimensions...</option>
                </select>
                <input
                  type="number"
                  id="custom-size-input"
                  min="<?= View::e($limits['min_size'] ?? 128) ?>"
                  max="<?= View::e($limits['max_size'] ?? 2048) ?>"
                  value="1000"
                  aria-label="Custom pixel size"
                  class="form-input text-xs font-mono"
                >
              </div>
            </div>

            <div>
              <label for="export-filename-input" class="block text-xs font-semibold mb-1">Download Filename (Optional)</label>
              <input type="text" id="export-filename-input" placeholder="qubiq-url-qr" maxlength="70" class="form-input text-xs font-mono">
            </div>
          </div>

          <!-- Primary Action & Download Buttons -->
          <div class="space-y-2 pt-1">
            <button type="button" id="generate-qr-btn" class="btn-primary w-full py-3 text-sm font-bold shadow-md shadow-emerald-600/15">
              Create QR Code
            </button>

            <div class="grid grid-cols-2 gap-2">
              <button type="button" data-export-format="png" class="btn-export btn-export-featured">
                Download PNG
              </button>
              <button type="button" data-export-format="svg" class="btn-export">
                Download SVG
              </button>
              <button type="button" data-export-format="pdf" class="btn-export">
                Download PDF
              </button>
              <button type="button" data-export-format="eps" class="btn-export">
                Download EPS
              </button>
            </div>

            <!-- More Export Formats & Clipboard Utilities -->
            <div class="grid grid-cols-4 gap-1.5 pt-1">
              <button type="button" data-export-format="webp" class="btn-utility" title="Export optimized WebP">WebP</button>
              <button type="button" data-export-format="jpeg" class="btn-utility" title="Export opaque JPEG">JPEG</button>
              <button type="button" id="copy-payload-btn" class="btn-utility" title="Copy raw encoded QR string">Copy Text</button>
              <button type="button" id="copy-image-btn" class="btn-utility" title="Copy PNG QR image to clipboard">Copy PNG</button>
            </div>
          </div>

          <!-- QR Technical Diagnostics Panel -->
          <div class="pt-3 border-t border-[var(--border-subtle)] text-xs space-y-1.5">
            <div class="font-semibold text-[var(--text-muted)] uppercase tracking-wider text-[11px]">QR Matrix Information</div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-[var(--text-muted)]">
              <div>Type: <strong id="info-type" class="text-[var(--text-primary)] uppercase"><?= View::e($initialMeta['content_type'] ?? 'url') ?></strong></div>
              <div>Payload: <strong id="info-bytes" class="text-[var(--text-primary)]"><?= View::e($initialMeta['byte_length'] ?? 32) ?> B</strong></div>
              <div>Version: <strong id="info-version" class="text-[var(--text-primary)]">v<?= View::e($initialMeta['version'] ?? 3) ?></strong></div>
              <div>ECC Level: <strong id="info-ecc" class="text-[var(--text-primary)]"><?= View::e($initialMeta['ecc_level'] ?? 'M') ?></strong></div>
              <div>Matrix: <strong id="info-matrix" class="text-[var(--text-primary)]"><?= View::e($initialMeta['symbol_modules'] ?? 29) ?>×<?= View::e($initialMeta['symbol_modules'] ?? 29) ?></strong></div>
              <div>Export: <strong id="info-dimensions" class="text-[var(--text-primary)]">1000×1000 px</strong></div>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </section>

  <!-- Features & Open-Source Principles Section -->
  <section id="features" class="border-t border-[var(--border-subtle)] bg-[var(--bg-surface)]/60 py-14 mt-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
      <div class="max-w-2xl">
        <h2 class="text-2xl font-extrabold tracking-tight">Built for Privacy, Print Precision &amp; Open Source</h2>
        <p class="text-sm text-[var(--text-muted)] mt-1">
          Every QR code is encoded server-side in PHP using ISO/IEC 18004 matrices and rendered natively into true vector and high-DPI raster formats.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="card-panel p-5 space-y-2">
          <h3 class="font-bold text-base">Stateless Static QR Codes</h3>
          <p class="text-xs text-[var(--text-muted)] leading-relaxed">
            Static QR codes embed your URL, Wi-Fi credentials, vCard, or payment URI directly into the symbol. Zero database writes, zero expiration dates, and zero dependency on our domain staying online.
          </p>
        </div>
        <div class="card-panel p-5 space-y-2">
          <h3 class="font-bold text-base">True Vector Print Pipeline</h3>
          <p class="text-xs text-[var(--text-muted)] leading-relaxed">
            Export genuine SVG, cubic-Bézier PDF, and PostScript Level 2 EPS files alongside supersampled PNG, WebP, and JPEG rasters — never mislabeled screenshots.
          </p>
        </div>
        <div class="card-panel p-5 space-y-2">
          <h3 class="font-bold text-base">Token-Protected Dynamic Links &amp; API</h3>
          <p class="text-xs text-[var(--text-muted)] leading-relaxed">
            Need editable destinations or daily scan counts without creating an account? Enable Dynamic QR to receive a cryptographic ownership secret, or automate generation via the OpenAPI 3.1 REST API.
          </p>
        </div>
      </div>
    </div>
  </section>
</main>

<script id="qubiq-boot-data" type="application/json"><?= $bootJson ?></script>
<script type="module" src="<?= View::e($bp) ?>/assets/js/app.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>

