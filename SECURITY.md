# Security Policy

## Supported Versions

| Version | PHP Compatibility | Security Updates |
|---|---|---|
| `1.0.x` | PHP 8.2, 8.3, 8.4+ | Supported |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please use GitHub's **Private Vulnerability Reporting** ("Report a vulnerability" under the **Security** tab of the repository) or contact the maintainers privately with:

1. A clear description of the vulnerability and affected component/endpoint.
2. Minimal proof-of-concept steps or HTTP request payload to reproduce.
3. Potential impact assessment.

We aim to acknowledge reports within **48 hours** and publish a coordinated patch and advisory within **14 days** for confirmed issues.

## Built-in Security Posture

- **Stateless Static QR Codes**: Zero database storage of private QR payloads.
- **Hashed Anonymous Dynamic Tokens**: 256-bit ownership secrets (`qubiq_own_...`) are hashed with SHA-256 (`hash_equals` verification) and kept out of URL query strings via browser `#token=` fragments and `Authorization: Bearer` headers.
- **Strict Upload Verification**: SVG logo uploads and remote logo URLs are rejected server-side; raster logos (`PNG`, `JPEG`, `WebP`) undergo `finfo` MIME validation, pixel-count limits, and clean GD RGBA re-encoding to strip metadata and polyglots.
- **Redirect Abuse Defenses**: Public dynamic redirects block unsafe schemes (`javascript:`, `data:`, `file:`), credential-embedded URLs (`user@host`), private/loopback/metadata IP ranges, and self-redirect loops.

