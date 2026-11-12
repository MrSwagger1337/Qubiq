CREATE TABLE IF NOT EXISTS dynamic_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    short_code TEXT NOT NULL UNIQUE,
    destination_url TEXT NOT NULL,
    token_hash TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    scan_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    last_scanned_at TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_dynamic_links_short_code ON dynamic_links (short_code);

CREATE TABLE IF NOT EXISTS dynamic_scan_rollups (
    link_id INTEGER NOT NULL,
    scan_date TEXT NOT NULL,
    device_category TEXT NOT NULL,
    country_code TEXT NOT NULL DEFAULT 'XX',
    scans INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (link_id, scan_date, device_category, country_code),
    FOREIGN KEY (link_id) REFERENCES dynamic_links(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_scan_rollups_link_date ON dynamic_scan_rollups (link_id, scan_date);

