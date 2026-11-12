# Production Deployment Guide

Qubiq supports three production deployment topologies:
1. **Shared Hosting** (cPanel / DirectAdmin / Plesk / LiteSpeed — PHP 8.2+, no Node.js)
2. **Linux VPS / Dedicated Server** (Ubuntu/Debian + Nginx + PHP-FPM + Let's Encrypt HTTPS)
3. **Containerized Deployment** (Docker & Docker Compose)

---

## Pre-Deployment Checklist

1. Set production flags in `.env`:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://qr.example.com
   APP_KEY=<64-char-hex-secret>
   ```
   Generate `APP_KEY` with PHP:
   ```bash
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```
2. Install production PHP dependencies without dev tools:
   ```bash
   composer install --no-dev --optimize-autoloader --classmap-authoritative
   ```
3. Ensure `storage/` and its subdirectories (`storage/database`, `storage/logs`, `storage/cache`, `storage/tmp`) are writable by the PHP user and **not publicly readable over HTTP**.

---

## Topology A: Shared Hosting (Apache / LiteSpeed)

### Best Option — Custom Document Root (`/public`)
In cPanel **Domains** -> set **Document Root** to `/home/<user>/qubiq/public`. Only `public/` sits inside the web root; `.env`, `storage/`, `vendor/`, and `app/` reside safely outside web space.

### Fallback Option — Locked `public_html/` or Subdirectory (`public_html/qr/`)
When your host does not allow changing Document Root:
1. Upload the project into `public_html/` (or `public_html/qr/`).
2. Confirm both `public_html/.htaccess` and `public_html/public/.htaccess` exist.
3. Verify external protection immediately after uploading:
   - Visiting `https://yourdomain.com/.env` must return `403 Forbidden`.
   - Visiting `https://yourdomain.com/storage/database/qubiq.sqlite` must return `403 Forbidden`.
   - Visiting `https://yourdomain.com/vendor/autoload.php` must return `403 Forbidden`.
   - Visiting `https://yourdomain.com/api/v1/health` must return `200 OK` JSON.

---

## Topology B: Ubuntu + Nginx + PHP-FPM + HTTPS

### 1. Directory & Permissions

```bash
sudo mkdir -p /var/www/qubiq
# Copy release files to /var/www/qubiq
cd /var/www/qubiq
composer install --no-dev --optimize-autoloader --classmap-authoritative
php database/migrate.php
sudo chown -R www-data:www-data /var/www/qubiq/storage
sudo chmod -R 770 /var/www/qubiq/storage
```

### 2. Nginx Server Block (`/etc/nginx/sites-available/qubiq.conf`)

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name qr.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name qr.example.com;

    root /var/www/qubiq/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/qr.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/qr.example.com/privkey.pem;

    client_max_body_size 4M;
    server_tokens off;

    # Cache immutable static assets
    location /assets/ {
        expires 7d;
        add_header Cache-Control "public, max-age=604800";
        try_files $uri =404;
    }

    # Deny dotfiles (except ACME challenge)
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock; # or php8.3-fpm.sock / php8.4-fpm.sock
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }
}
```

### 3. Recommended `php.ini` Hardening

```ini
expose_php = Off
display_errors = Off
log_errors = On
upload_max_filesize = 3M
post_max_size = 4M
max_file_uploads = 2
memory_limit = 128M
max_execution_time = 15
```

---

## Reverse Proxy Configuration (Cloudflare / Load Balancer)

If running behind Cloudflare or an internal reverse proxy, configure `TRUSTED_PROXIES` in `.env` with the proxy's IP address(es) so `HttpRequest::clientIp()` and `isHttps()` honor `X-Forwarded-For` and `X-Forwarded-Proto` safely without IP-spoofing risk:

```ini
TRUSTED_PROXIES=127.0.0.1
```

---

## SQLite Backup & Restoration (Dynamic QR Mode)

Qubiq configures SQLite with `PRAGMA journal_mode = WAL;` and `PRAGMA foreign_keys = ON;`.

### Safe Online Backup

```bash
sqlite3 /var/www/qubiq/storage/database/qubiq.sqlite ".backup '/var/backups/qubiq-$(date +%F).sqlite'"
```

*(If `sqlite3` CLI is unavailable on shared hosting, run a tiny PHP one-liner or copy `qubiq.sqlite` during a low-traffic window.)*

### Restoration

```bash
cp /var/backups/qubiq-2026-11-12.sqlite /var/www/qubiq/storage/database/qubiq.sqlite
rm -f /var/www/qubiq/storage/database/qubiq.sqlite-wal /var/www/qubiq/storage/database/qubiq.sqlite-shm
chown www-data:www-data /var/www/qubiq/storage/database/qubiq.sqlite
```

