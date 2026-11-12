# Security & Privacy Architecture

Qubiq is built privacy-first and security-hardened against OWASP Top 10 web and file-processing risks.

---

## 1. Zero-Storage Static QR Generation

- Static QR previews, validations, and exports execute entirely in RAM.
- User URLs, Wi-Fi passwords, vCard contacts, and Bitcoin addresses are **never written to SQLite or application logs**.

---

## 2. Upload & Image Processing Security (`LogoProcessorService`)

Uploading untrusted images is one of the highest-risk attack surfaces in QR generators. Qubiq enforces defense-in-depth controls:

1. **No Arbitrary Remote URLs (SSRF Prevention)**: Qubiq never fetches remote `logo_url` links server-side. Logos must be uploaded directly as bounded binary files.
2. **SVG Logos Explicitly Rejected**: Because embedded `<svg>` files can carry XSS payloads, external entity references (XXE), and CSS/font exfiltration vectors — and MIT-incompatible GPL sanitizers would violate licensing — Qubiq rejects SVG uploads server-side and accepts only `image/png`, `image/jpeg`, and `image/webp`.
3. **Magic-Byte Inspection (`finfo`)**: Extensions and client `Content-Type` headers are ignored; actual file bytes are verified via `finfo(FILEINFO_MIME_TYPE)` and `getimagesize()`.
4. **Decompression-Bomb ("Pixel Flood") Protection**: Before calling GD decoders, Qubiq inspects image header dimensions (`width`, `height`) and rejects images exceeding `LOGO_MAX_BYTES` (`2 MB`), `LOGO_MAX_DIMENSION` (`2048 px`), or `LOGO_MAX_PIXELS` (`4,194,304` total pixels).
5. **Pixel Re-Encoding & Metadata Stripping**: Accepted logos are decoded into a fresh truecolor RGBA canvas, scaled to target bounds, and re-encoded as clean PNG bytes in memory — stripping EXIF metadata, polyglot payloads, and trailing garbage bytes.

---

## 3. Payload & URL Scheme Hardening (`UrlSafetyValidator`)

- **Blocked Executable Schemes**: `javascript:`, `data:`, `vbscript:`, `file:`, `blob:`, `jar:`, `filesystem:`, and `about:` are strictly rejected across URL, Social, Dynamic Link, and Custom URI encoders.
- **Custom URI Allowlist**: `CustomUriPayload` permits only syntactically valid, non-executable custom schemes (e.g., `myapp://`, `spotify:`, `upi://`) and rejects control bytes (`\x00–\x1F`, `\x7F`).
- **Dynamic QR Anti-Phishing Controls**:
  - Destination URLs are restricted strictly to `http://` and `https://` with valid hostnames (no credentials-in-URL `user:pass@host` tricks, no localhost/loopback/private-network targets).
  - Administrators can configure `DYNAMIC_QR_BLOCKED_DOMAINS` and optional `DYNAMIC_QR_SAFE_INTERSTITIAL=true` (displays destination preview before redirecting).

---

## 4. Anonymous Dynamic QR Ownership & Privacy-Respecting Analytics

- **No Accounts or Passwords**: Creating a dynamic link issues a **256-bit cryptographically random token** (`random_bytes(32)`).
- **Hashed Storage**: Only `hash('sha256', $token)` is persisted in SQLite (`token_hash`), verified in constant time via `hash_equals()`.
- **URL Fragment Token Transport**: The management UI reads `#token=...` from `window.location.hash` and sends it in `Authorization: Bearer ...` API calls, preventing the secret token from appearing in server access logs or `Referer` headers.
- **Zero-PII Aggregate Analytics**:
  - Scans increment aggregate daily rollup counters (`dynamic_link_daily_stats`) and coarse device categories (`mobile`, `tablet`, `desktop`, `other`).
  - Raw visitor IP addresses, cookies, browser fingerprints, and full `User-Agent` strings are **never stored**.

---

## 5. HTTP Security Headers & Abuse Prevention

Applied automatically on every response via `SecurityHeadersMiddleware`:
- **Strict `Content-Security-Policy`**: `default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'` — zero `'unsafe-inline'` JavaScript execution! Server boot configuration is serialized via `<script type="application/json">`.
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
- `Strict-Transport-Security` automatically emitted over HTTPS
- **HMAC Rate Limiting**: Client IPs are hashed with `APP_KEY` (`hash_hmac('sha256', $ip, $secret)`) for rate-limit buckets (`preview`, `export`, `api`, `dynamic_create`), enforcing abuse limits without logging plaintext IP addresses to cache files.

---

## Reporting Vulnerabilities

Please see [SECURITY.md](../SECURITY.md) for responsible disclosure instructions.

