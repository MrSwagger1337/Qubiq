# Contributing to Qubiq

Thank you for helping improve **Qubiq**! We welcome bug fixes, accessibility improvements, payload tests, translations, and documentation enhancements.

## Core Engineering Guardrails

1. **PHP 8.2+ Compatibility**: Target PHP `8.2`, `8.3`, and `8.4+`. Always declare `declare(strict_types=1);` and avoid PHP 8.3/8.4-only syntax so shared-hosting users on PHP 8.2 run effortlessly.
2. **Zero Server-Side Node.js Requirement**: Production CSS (`public/assets/css/app.css`) and ES modules (`public/assets/js/*.js`) are committed to Git so shared-hosting deployments never require Node.js. If you modify `resources/css/app.css` or `resources/js/*.js`, run `npm run check` locally before committing.
3. **Privacy-First**: Static QR generation must never persist user payloads, set tracking cookies, or embed third-party scripts.
4. **Permissive Licenses Only**: All runtime Composer dependencies must be MIT, BSD, or Apache-2.0 compatible.

## Local Development Workflow

```bash
composer install
cp .env.example .env
php -S localhost:8000 -t public public/index.php
```

### Quality Checks Before Submitting a PR

```bash
composer format   # Auto-format code to PSR-12 via Laravel Pint
composer check    # Runs Pint --test, PHPStan Level 8, and PHPUnit
npm run check     # (If editing CSS/JS) Validates JS syntax & rebuilds public/assets/
```

## Adding Translations (i18n)

1. Copy `resources/lang/en.php` to `resources/lang/<locale>.php` (e.g., `fr.php`, `ar.php`, `de.php`).
2. Translate string values while preserving `:placeholder` tokens (such as `:bytes`, `:seconds`, `:ratio`).
3. Set `APP_LOCALE=<locale>` in `.env` and verify layout alignment.

