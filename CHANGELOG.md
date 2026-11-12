# Changelog

All notable changes to **Qubiq** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-12

### Added
- Authoritative PHP 8.2+ QR Code Studio powered by `chillerlan/php-qrcode` v6.
- 21 dedicated payload encoders (`URL`, `Text`, `Email`, `Phone`, `SMS`, `vCard 3.0`, `MeCard`, `Location`, `Wi-Fi`, `Calendar Event`, `Bitcoin BIP-21`, `WhatsApp`, `Telegram`, `Facebook`, `X/Twitter`, `YouTube`, `Instagram`, `LinkedIn`, `PayPal`, `App Links`, `Custom URI`).
- Capability-aware rendering pipeline supporting genuine vector (`SVG`, `PDF`, `EPS`) and 2× supersampled raster (`PNG`, `WebP`, `JPEG`) downloads.
- Full QR styling engine: solid, linear & radial gradients, custom corner finder outer/inner shapes & colors, 6 scan-safe module shapes, sanitized logo embedding, readability contrast & logo coverage advisor, and 7 presets.
- Optional accountless Dynamic QR redirects (`/r/{code}`) with SHA-256 hashed ownership secrets, token rotation, and zero-PII daily rollup analytics.
- Public REST API v1 (`/api/v1/*`) and OpenAPI 3.1 specification (`/openapi.json`).
- Shared-hosting-ready deployment (zero Node.js required on server, hardened `.htaccess` guards) alongside Docker Compose and GitHub Actions CI.

