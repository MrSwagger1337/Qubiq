# Installation Guide

Qubiq is engineered to run out-of-the-box on **PHP 8.2, 8.3, and 8.4+** across **Shared Hosting (cPanel, DirectAdmin, Plesk, LiteSpeed)**, **Local PHP CLI**, **VPS (Nginx / Apache + PHP-FPM)**, and **Docker** — **with zero Node.js required on the server**.

All production CSS (`public/assets/css/app.css`) and Vanilla ES module JavaScript (`public/assets/js/*.js`) are pre-built and included in the repository.

---

## System Requirements

| Requirement | Minimum / Recommended | Notes |
| --- | --- | --- |
| **PHP** | `8.2.0+` (`8.2`, `8.3`, or `8.4+`) | Strict typing, `composer.lock` pinned for PHP 8.2+ compatibility |
| **Required PHP Extensions** | `fileinfo`, `gd`, `json`, `mbstring`, `pdo` | Standard on almost all shared hosts; `gd` enables PNG/JPEG/WebP raster & logo processing |
| **Optional PHP Extensions** | `pdo_sqlite`, `intl` | `pdo_sqlite` is only required when `DYNAMIC_QR_ENABLED=true`; `intl` enables IDN punycode domain normalization |
| **Composer** | `2.6+` | Can run locally before uploading `vendor/` to shared hosting |
| **Node.js** | **Not required in production** | Optional (`Node 20+`) only if modifying `resources/css/` or `resources/js/` locally |

---

## Option 1: Shared Hosting (cPanel / DirectAdmin / Plesk — Zero Node.js)

### Step 1: Prepare Release Locally (or Use Release Archive)

On your local computer (or CI):

```bash
git clone https://github.com/MrSwagger1337/Qubiq.git
cd Qubiq
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Edit `.env` and set your domain and random `APP_KEY`:

```ini
APP_NAME="Qubiq"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://qr.yourdomain.com
APP_KEY=replace-with-a-random-32-byte-hex-secret
```

*(Note: No `npm install` or `npm run build` step is needed — `public/assets/` is already compiled!)*

### Step 2A: Recommended Shared Hosting Layout (Custom Document Root)

If creating an Addon Domain or Subdomain (`qr.yourdomain.com`):

1. Upload the project files to `/home/username/qubiq/`.
2. Point the domain's **Document Root** to `/home/username/qubiq/public`.
3. Ensure `/home/username/qubiq/storage` is writable (`chmod -R 775 storage`).

### Step 2B: Locked `public_html` Shared Hosting Layout (Root or Subfolder)

If your hosting plan locks the primary domain to `public_html/` (or you want to install inside `public_html/qr/`):

1. Upload the entire project (including the root `.htaccess` and root `index.php`) directly into `public_html/` (or `public_html/qr/`).
2. Ensure `storage/` is writable (`chmod -R 775 storage`).
3. Verify hidden files (`.htaccess`, `.env`) were uploaded in File Manager ("Show Hidden Files").

Qubiq includes triple-layered protection for `public_html/` installations:
- Root `.htaccess` blocks HTTP access to `.env`, dotfiles, `.sqlite`, `.log`, `composer.*`, and internal directories (`app/`, `bootstrap/`, `config/`, `database/`, `resources/`, `routes/`, `storage/`, `tests/`, `vendor/`).
- Dedicated `Require all denied` `.htaccess` guards inside every internal directory (`app/`, `bootstrap/`, `config/`, `database/`, `resources/`, `routes/`, `storage/`, `tests/`).
- Automatic subdirectory detection in `App\Support\HttpRequest` so both `/` and `/qr` work transparently.

*(Optional)* Initialize the SQLite database if SSH/Terminal is available (otherwise Qubiq auto-initializes it on first dynamic QR creation):

```bash
php database/migrate.php
```

---

## Option 2: Local Development (PHP Built-in Server)

```bash
git clone https://github.com/MrSwagger1337/Qubiq.git
cd Qubiq
composer install
cp .env.example .env
php database/migrate.php
composer serve
```

Open `http://localhost:8000` in your browser.

---

## Option 3: Docker & Docker Compose

```bash
git clone https://github.com/MrSwagger1337/Qubiq.git
cd Qubiq
cp .env.example .env
docker compose up -d --build
```

Open `http://localhost:8080` in your browser. Persistent SQLite data and logs are stored in the `qubiq-storage` Docker volume.

---

## Optional Frontend Asset Development

Only needed if you edit `resources/css/app.css` or `resources/js/*.js`:

```bash
npm install
npm run build
npm run check
```

Commit the updated `public/assets/` files so shared-hosting users continue to enjoy zero-Node deployments.

