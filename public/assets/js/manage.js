import { notify } from './notify.js';

const bootEl = document.getElementById('qubiq-manage-boot');
const BOOT = bootEl ? JSON.parse(bootEl.textContent || '{}') : {};
const BASE_PATH = BOOT.basePath || '';
const SHORT_CODE = BOOT.shortCode || '';

let activeToken = '';

function extractHashToken() {
    const rawHash = window.location.hash.replace(/^#/, '');
    const params = new URLSearchParams(rawHash);
    return (params.get('token') || '').trim();
}

async function init() {
    const hashToken = extractHashToken();
    if (hashToken) {
        activeToken = hashToken;
        await loadStatsAndUnlock();
    }

    document.getElementById('manage-auth-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('manage-token-input');
        const candidate = input?.value?.trim() || '';
        if (!candidate) {
            return;
        }
        activeToken = candidate;
        window.location.hash = `token=${encodeURIComponent(candidate)}`;
        await loadStatsAndUnlock();
    });

    document.getElementById('manage-update-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const destInput = document.getElementById('manage-dest-input');
        const activeCb = document.getElementById('manage-active-checkbox');

        try {
            const res = await fetch(`${BASE_PATH}/api/v1/links/${encodeURIComponent(SHORT_CODE)}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: `Bearer ${activeToken}`,
                },
                body: JSON.stringify({
                    destination_url: destInput?.value?.trim() || '',
                    is_active: Boolean(activeCb?.checked),
                }),
            });
            const body = await res.json();
            if (!res.ok || !body.ok) {
                throw new Error(body?.error?.message || 'Update failed.');
            }
            populateDashboard(body.data);
            notify('Dynamic QR destination and status updated!');
        } catch (err) {
            notify(err instanceof Error ? err.message : 'Unable to save changes.', 'error');
        }
    });

    document.getElementById('rotate-token-btn')?.addEventListener('click', async () => {
        if (!window.confirm('Rotate ownership token? Your previous management bookmark will immediately stop working.')) {
            return;
        }
        try {
            const res = await fetch(`${BASE_PATH}/api/v1/links/${encodeURIComponent(SHORT_CODE)}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: `Bearer ${activeToken}`,
                },
                body: JSON.stringify({ rotate_token: true }),
            });
            const body = await res.json();
            if (!res.ok || !body.ok) {
                throw new Error(body?.error?.message || 'Token rotation failed.');
            }
            if (body.data?.ownership_token) {
                activeToken = body.data.ownership_token;
                window.location.hash = `token=${encodeURIComponent(activeToken)}`;
                const box = document.getElementById('rotated-token-box');
                const val = document.getElementById('rotated-token-value');
                if (val) val.value = activeToken;
                box?.classList.remove('hidden');
                notify('Ownership token rotated. Save your new token now!');
            }
        } catch (err) {
            notify(err instanceof Error ? err.message : 'Token rotation failed.', 'error');
        }
    });

    document.getElementById('delete-link-btn')?.addEventListener('click', async () => {
        if (!window.confirm('Permanently delete this dynamic QR link and all aggregated scan statistics? Printed QR codes using this short link will stop redirecting.')) {
            return;
        }
        try {
            const res = await fetch(`${BASE_PATH}/api/v1/links/${encodeURIComponent(SHORT_CODE)}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${activeToken}`,
                },
            });
            const body = await res.json();
            if (!res.ok || !body.ok) {
                throw new Error(body?.error?.message || 'Deletion failed.');
            }
            notify('Dynamic QR link deleted permanently.');
            window.setTimeout(() => {
                window.location.href = BASE_PATH || '/';
            }, 900);
        } catch (err) {
            notify(err instanceof Error ? err.message : 'Deletion failed.', 'error');
        }
    });
}

async function loadStatsAndUnlock() {
    const authCard = document.getElementById('manage-auth-card');
    const authErr = document.getElementById('manage-auth-error');
    const dashboard = document.getElementById('manage-dashboard');

    try {
        const res = await fetch(`${BASE_PATH}/api/v1/links/${encodeURIComponent(SHORT_CODE)}/stats`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${activeToken}`,
            },
        });
        const body = await res.json();
        if (!res.ok || !body.ok) {
            throw new Error(body?.error?.message || 'Invalid ownership token.');
        }

        authErr?.classList.add('hidden');
        authCard?.classList.add('hidden');
        dashboard?.classList.remove('hidden');
        populateDashboard(body.data);
    } catch (err) {
        if (authErr) {
            authErr.textContent = err instanceof Error ? err.message : 'Authentication failed.';
            authErr.classList.remove('hidden');
        }
        authCard?.classList.remove('hidden');
        dashboard?.classList.add('hidden');
    }
}

function populateDashboard(data) {
    const setTxt = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.textContent = String(v);
    };

    setTxt('stat-total', data.total_scans ?? 0);
    setTxt('stat-today', data.analytics?.today_scans ?? 0);
    setTxt('stat-week', data.analytics?.weekly_scans ?? 0);
    setTxt('stat-month', data.analytics?.monthly_scans ?? 0);

    const destInput = document.getElementById('manage-dest-input');
    if (destInput) destInput.value = data.destination_url || '';

    const activeCb = document.getElementById('manage-active-checkbox');
    if (activeCb) activeCb.checked = Boolean(data.is_active);

    const statusPill = document.getElementById('manage-status-pill');
    if (statusPill) {
        statusPill.textContent = data.is_active ? 'Active' : 'Paused';
    }

    const shortLink = document.getElementById('manage-short-link');
    if (shortLink) {
        shortLink.href = data.short_url || '#';
        shortLink.textContent = data.short_url || '';
    }

    setTxt('manage-created-at', data.created_at ? data.created_at.slice(0, 10) : '—');
    setTxt('manage-last-scan', data.last_scanned_at ? data.last_scanned_at.replace('T', ' ').slice(0, 19) : 'Never');

    renderBarList('daily-chart-list', (data.analytics?.daily_series || []).map((r) => ({ label: r.date, count: r.scans })));
    renderBarList('device-breakdown-list', (data.analytics?.devices || []).map((r) => ({ label: r.device.toUpperCase(), count: r.scans })));
}

function renderBarList(containerId, items) {
    const el = document.getElementById(containerId);
    if (!el) {
        return;
    }
    el.innerHTML = '';

    if (!items.length) {
        const empty = document.createElement('p');
        empty.className = 'text-[var(--text-muted)] italic';
        empty.textContent = 'No scans recorded yet.';
        el.appendChild(empty);
        return;
    }

    const max = Math.max(1, ...items.map((i) => i.count));
    for (const item of items) {
        const row = document.createElement('div');
        row.className = 'space-y-1';

        const top = document.createElement('div');
        top.className = 'flex justify-between font-mono';
        const lbl = document.createElement('span');
        lbl.textContent = item.label;
        const cnt = document.createElement('span');
        cnt.className = 'font-bold';
        cnt.textContent = String(item.count);
        top.append(lbl, cnt);

        const track = document.createElement('div');
        track.className = 'h-2 rounded-full bg-[var(--bg-muted)] overflow-hidden';
        const bar = document.createElement('div');
        bar.className = 'h-full rounded-full bg-emerald-500';
        bar.style.width = `${Math.max(4, Math.round((item.count / max) * 100))}%`;
        track.appendChild(bar);

        row.append(top, track);
        el.appendChild(row);
    }
}

document.addEventListener('DOMContentLoaded', init);

