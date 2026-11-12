<?php
declare(strict_types=1);

use App\Support\View;

$pageTitle = 'Developer REST API — ' . ($appName ?? 'Qubiq');
$activeNav = 'api';
require __DIR__ . '/partials/header.php';
$bp = rtrim($basePath ?? '', '/');
$apiBase = rtrim($baseUrl ?? 'http://localhost:8000', '/');
?>
<main id="main-content" class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div class="space-y-2">
      <span class="badge-pill">Public REST API v1 • OpenAPI 3.1</span>
      <h1 class="text-3xl font-extrabold tracking-tight">Developer API Reference</h1>
      <p class="text-sm text-[var(--text-muted)] max-w-2xl">
        Generate customized QR codes programmatically with zero API keys or user accounts. Protected by sensible rate limits to keep the service fast for everyone.
      </p>
    </div>
    <a href="<?= View::e($bp) ?>/openapi.json" target="_blank" rel="noopener noreferrer" class="btn-secondary text-xs">
      View OpenAPI 3.1 JSON Spec ↗
    </a>
  </div>

  <!-- Endpoints Table -->
  <section class="card-panel p-6 space-y-4">
    <h2 class="text-lg font-bold">Endpoints Overview</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-[var(--border-subtle)] text-[var(--text-muted)]">
            <th class="py-2 pr-3">Method</th>
            <th class="py-2 pr-4">Endpoint</th>
            <th class="py-2 pr-4">Auth</th>
            <th class="py-2">Description</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border-subtle)] font-mono">
          <tr><td class="py-2.5 pr-3 text-emerald-600 font-bold">GET</td><td class="py-2.5 pr-4">/api/v1/health</td><td class="py-2.5 pr-4 font-sans">None</td><td class="py-2.5 font-sans">Service health and supported export formats.</td></tr>
          <tr><td class="py-2.5 pr-3 text-sky-600 font-bold">POST</td><td class="py-2.5 pr-4">/api/v1/qr/validate</td><td class="py-2.5 pr-4 font-sans">None</td><td class="py-2.5 font-sans">Validate payload &amp; styling, returning QR version, byte count, and scan warnings.</td></tr>
          <tr><td class="py-2.5 pr-3 text-sky-600 font-bold">POST</td><td class="py-2.5 pr-4">/api/v1/qr/preview</td><td class="py-2.5 pr-4 font-sans">None</td><td class="py-2.5 font-sans">Render SVG preview markup plus matrix diagnostics.</td></tr>
          <tr><td class="py-2.5 pr-3 text-sky-600 font-bold">POST</td><td class="py-2.5 pr-4">/api/v1/qr/generate</td><td class="py-2.5 pr-4 font-sans">None</td><td class="py-2.5 font-sans">Generate QR in JSON with base64 <code class="font-mono">data_uri</code> (and optional <code class="font-mono">dynamic: true</code>).</td></tr>
          <tr><td class="py-2.5 pr-3 text-sky-600 font-bold">POST</td><td class="py-2.5 pr-4">/api/v1/qr/export</td><td class="py-2.5 pr-4 font-sans">None</td><td class="py-2.5 font-sans">Download raw binary file (<code class="font-mono">png</code>, <code class="font-mono">svg</code>, <code class="font-mono">pdf</code>, <code class="font-mono">eps</code>, <code class="font-mono">webp</code>, <code class="font-mono">jpeg</code>).</td></tr>
          <tr><td class="py-2.5 pr-3 text-sky-600 font-bold">POST</td><td class="py-2.5 pr-4">/api/v1/links</td><td class="py-2.5 pr-4 font-sans">None</td><td class="py-2.5 font-sans">Create an anonymous dynamic short link &amp; receive one-time ownership token.</td></tr>
          <tr><td class="py-2.5 pr-3 text-emerald-600 font-bold">GET</td><td class="py-2.5 pr-4">/api/v1/links/{id}/stats</td><td class="py-2.5 pr-4 font-sans">Bearer Token</td><td class="py-2.5 font-sans">Inspect destination and privacy-preserving scan statistics.</td></tr>
          <tr><td class="py-2.5 pr-3 text-amber-600 font-bold">PATCH</td><td class="py-2.5 pr-4">/api/v1/links/{id}</td><td class="py-2.5 pr-4 font-sans">Bearer Token</td><td class="py-2.5 font-sans">Update destination URL, active status, or rotate ownership token.</td></tr>
          <tr><td class="py-2.5 pr-3 text-rose-600 font-bold">DELETE</td><td class="py-2.5 pr-4">/api/v1/links/{id}</td><td class="py-2.5 pr-4 font-sans">Bearer Token</td><td class="py-2.5 font-sans">Delete dynamic QR code and analytics permanently.</td></tr>
        </tbody>
      </table>
    </div>
    <p class="text-xs text-[var(--text-muted)]">
      <strong>Anonymous Rate Limits:</strong> General API: <?= (int) ($rateLimits['api'] ?? 60) ?> req/min · Exports: <?= (int) ($rateLimits['export'] ?? 60) ?> req/min · Dynamic Link Creation: <?= (int) ($rateLimits['dynamic_create_hourly'] ?? 20) ?> req/hour per client.
    </p>
  </section>

  <!-- Code Examples -->
  <section class="card-panel p-6 space-y-5">
    <h2 class="text-lg font-bold">Quickstart Examples</h2>

    <div class="space-y-2">
      <h3 class="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)]">1. cURL — Download Customized PNG</h3>
      <pre class="code-block"><code>curl -X POST "<?= View::e($apiBase) ?>/api/v1/qr/export" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "wifi",
    "data": { "ssid": "Studio-5G", "encryption": "WPA", "password": "SuperSecretPassword" },
    "design": {
      "ecc": "H",
      "size": 1000,
      "foreground_mode": "linear",
      "foreground_color": "#0f172a",
      "foreground_gradient_end": "#059669",
      "module_shape": "rounded",
      "finder_outer_shape": "rounded",
      "finder_inner_shape": "circle"
    },
    "format": "png"
  }' --output wifi-qr.png</code></pre>
    </div>

    <div class="space-y-2">
      <h3 class="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)]">2. JavaScript (Fetch ES2022+)</h3>
      <pre class="code-block"><code>const response = await fetch('<?= View::e($apiBase) ?>/api/v1/qr/generate', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    type: 'url',
    data: { url: 'https://example.com/welcome' },
    design: { ecc: 'M', size: 512, module_shape: 'circle' },
    format: 'svg'
  })
});

const { ok, data, error } = await response.json();
if (!ok) throw new Error(error.message);
document.querySelector('#qr-target').innerHTML = data.svg;</code></pre>
    </div>

    <div class="space-y-2">
      <h3 class="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)]">3. PHP 8.2+ (cURL)</h3>
      <pre class="code-block"><code>&lt;?php
$ch = curl_init('<?= View::e($apiBase) ?>/api/v1/qr/generate');
curl_setopt_array($ch, [
    CURLOPT_POST =&gt; true,
    CURLOPT_RETURNTRANSFER =&gt; true,
    CURLOPT_HTTPHEADER =&gt; ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS =&gt; json_encode([
        'type' =&gt; 'vcard',
        'data' =&gt; [
            'first_name' =&gt; 'Elena',
            'last_name' =&gt; 'Rostova',
            'email' =&gt; 'elena@example.com',
            'mobile_phone' =&gt; '+14155550199',
        ],
        'format' =&gt; 'png',
    ]),
]);
$result = json_decode((string) curl_exec($ch), true);
curl_close($ch);
// Base64 PNG ready in $result['data']['data_uri']</code></pre>
    </div>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>

