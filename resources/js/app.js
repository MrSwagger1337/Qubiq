import { DESIGN_PRESETS, SAFE_RANDOM_PALETTES, TYPE_FIELD_SCHEMAS } from './schemas.js';
import { notify } from './notify.js';

const PRESETS_STORAGE_KEY = 'qubiq_saved_design_presets_v1';

const bootEl = document.getElementById('qubiq-boot-data');
const BOOT = bootEl ? JSON.parse(bootEl.textContent || '{}') : {};
const BASE_PATH = BOOT.basePath || '';

const typesMap = new Map();
for (const item of BOOT.types || []) {
    typesMap.set(item.type, item);
}

const state = {
    activeType: 'url',
    formValuesByType: {},
    logoDataUri: null,
    dynamicLink: null,
    lastEncodedPayload: BOOT.initialPayload || '',
};

let debounceTimer = null;
let activeAbortController = null;

function init() {
    for (const [type, meta] of typesMap.entries()) {
        state.formValuesByType[type] = { ...(meta.sample || {}) };
    }

    setupAccordions();
    setupTypeTabs();
    setupColorControls();
    setupLogoControls();
    setupDesignControls();
    setupResolutionAndExports();
    setupDynamicQrControls();
    setupLocalPresetsAndJson();
    renderSavedPresets();

    renderActiveTypeForm(false);
    syncVisibilityStates();
}

function setupAccordions() {
    document.querySelectorAll('.accordion-header').forEach((header) => {
        header.addEventListener('click', () => {
            const expanded = header.getAttribute('aria-expanded') === 'true';
            const targetId = header.getAttribute('aria-controls');
            const body = targetId ? document.getElementById(targetId) : null;
            const chevron = header.querySelector('.accordion-chevron');

            header.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            if (body) {
                body.classList.toggle('hidden', expanded);
            }
            if (chevron) {
                chevron.classList.toggle('rotate-180', !expanded);
            }
        });
    });
}

function setupTypeTabs() {
    const primaryTabs = Array.from(document.querySelectorAll('#primary-type-tabs [data-qr-type]'));
    const moreBtn = document.getElementById('more-types-btn');
    const morePopover = document.getElementById('more-types-popover');
    const moreSearch = document.getElementById('more-types-search');
    const moreItems = Array.from(document.querySelectorAll('#more-types-list [data-qr-type]'));

    const selectType = (type) => {
        if (!typesMap.has(type)) {
            return;
        }
        state.activeType = type;

        primaryTabs.forEach((btn) => {
            const isMatch = btn.getAttribute('data-qr-type') === type;
            btn.setAttribute('aria-selected', isMatch ? 'true' : 'false');
            btn.classList.toggle('type-tab-active', isMatch);
        });

        const meta = typesMap.get(type);
        const isMore = meta && meta.category !== 'primary';
        const moreLabel = document.getElementById('more-types-label');
        if (moreBtn && moreLabel) {
            moreBtn.classList.toggle('type-tab-active', Boolean(isMore));
            moreLabel.textContent = isMore ? `More: ${meta.label}` : `More Types (${moreItems.length})`;
        }

        if (morePopover) {
            morePopover.classList.add('hidden');
            moreBtn?.setAttribute('aria-expanded', 'false');
        }

        const dynBox = document.getElementById('dynamic-qr-box');
        if (dynBox) {
            dynBox.classList.toggle('hidden', type !== 'url');
        }
        if (type !== 'url' && state.dynamicLink) {
            state.dynamicLink = null;
            document.getElementById('dynamic-credentials-panel')?.classList.add('hidden');
        }

        renderActiveTypeForm(true);
    };

    primaryTabs.forEach((btn, idx) => {
        btn.addEventListener('click', () => selectType(btn.getAttribute('data-qr-type') || 'url'));
        btn.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                e.preventDefault();
                const nextIdx = e.key === 'ArrowRight'
                    ? (idx + 1) % primaryTabs.length
                    : (idx - 1 + primaryTabs.length) % primaryTabs.length;
                primaryTabs[nextIdx].focus();
                selectType(primaryTabs[nextIdx].getAttribute('data-qr-type') || 'url');
            }
        });
    });

    moreItems.forEach((item) => {
        item.addEventListener('click', () => selectType(item.getAttribute('data-qr-type') || 'url'));
    });

    if (moreBtn && morePopover) {
        moreBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = morePopover.classList.toggle('hidden');
            moreBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            if (!isHidden) {
                moreSearch?.focus();
            }
        });

        document.addEventListener('click', (e) => {
            if (!morePopover.contains(e.target) && e.target !== moreBtn) {
                morePopover.classList.add('hidden');
                moreBtn.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                morePopover.classList.add('hidden');
                moreBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (moreSearch) {
        moreSearch.addEventListener('input', () => {
            const q = moreSearch.value.trim().toLowerCase();
            moreItems.forEach((btn) => {
                const lbl = btn.getAttribute('data-type-label') || '';
                btn.classList.toggle('hidden', q !== '' && !lbl.includes(q));
            });
        });
    }

    document.getElementById('fill-sample-btn')?.addEventListener('click', () => {
        const meta = typesMap.get(state.activeType);
        if (meta && meta.sample) {
            state.formValuesByType[state.activeType] = { ...meta.sample };
            renderActiveTypeForm(true);
            notify(`Loaded sample ${meta.label} data.`);
        }
    });
}

function renderActiveTypeForm(triggerPreview = true) {
    const container = document.getElementById('dynamic-form-fields');
    if (!container) {
        return;
    }

    const type = state.activeType;
    const meta = typesMap.get(type);
    const schema = TYPE_FIELD_SCHEMAS[type] || TYPE_FIELD_SCHEMAS.url;
    const values = state.formValuesByType[type] || {};

    const pill = document.getElementById('active-type-pill');
    if (pill && meta) {
        pill.textContent = meta.label;
    }

    const desc = document.getElementById('type-description-text');
    if (desc && meta) {
        desc.textContent = meta.description;
    }

    const urlBadge = document.getElementById('url-wrapper-badge');
    if (urlBadge && meta) {
        urlBadge.textContent = meta.is_url_wrapper ? 'Standard Web Link' : 'Native QR Schema';
    }

    const filenameInput = document.getElementById('export-filename-input');
    if (filenameInput && !filenameInput.dataset.userEdited) {
        filenameInput.placeholder = `qubiq-${type}-qr`;
    }

    container.innerHTML = '';
    const gridWrapper = document.createElement('div');
    gridWrapper.className = 'grid grid-cols-1 sm:grid-cols-2 gap-3';

    for (const field of schema) {
        const wrap = document.createElement('div');
        wrap.className = field.grid === 'half' ? 'col-span-1' : 'col-span-1 sm:col-span-2';
        const inputId = `qr-field-${type}-${field.name}`;
        const currentVal = values[field.name] ?? '';

        if (field.type === 'checkbox') {
            const label = document.createElement('label');
            label.className = 'inline-flex items-center gap-2 text-sm font-medium cursor-pointer pt-1';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.id = inputId;
            cb.checked = Boolean(currentVal);
            cb.className = 'rounded border-slate-300 text-emerald-600 focus:ring-emerald-500';
            cb.addEventListener('change', () => {
                state.formValuesByType[type][field.name] = cb.checked;
                schedulePreview();
            });
            const span = document.createElement('span');
            span.textContent = field.label;
            label.append(cb, span);
            wrap.appendChild(label);
        } else {
            const label = document.createElement('label');
            label.htmlFor = inputId;
            label.className = 'field-label';
            label.textContent = field.label;
            wrap.appendChild(label);

            let inputEl;
            if (field.type === 'textarea') {
                inputEl = document.createElement('textarea');
                inputEl.rows = field.rows || 3;
                inputEl.placeholder = field.placeholder || '';
                inputEl.value = String(currentVal);
            } else if (field.type === 'select') {
                inputEl = document.createElement('select');
                for (const opt of field.options || []) {
                    const o = document.createElement('option');
                    o.value = opt.value;
                    o.textContent = opt.label;
                    if (String(currentVal) === opt.value) {
                        o.selected = true;
                    }
                    inputEl.appendChild(o);
                }
            } else {
                inputEl = document.createElement('input');
                inputEl.type = field.type || 'text';
                inputEl.placeholder = field.placeholder || '';
                inputEl.value = String(currentVal);
            }

            inputEl.id = inputId;
            inputEl.className = 'form-input text-sm';
            inputEl.addEventListener('input', () => {
                state.formValuesByType[type][field.name] = inputEl.value;
                schedulePreview();
            });

            wrap.appendChild(inputEl);
        }

        gridWrapper.appendChild(wrap);
    }

    container.appendChild(gridWrapper);

    if (triggerPreview) {
        schedulePreview(60);
    }
}

function bindColorPair(pickerId, hexId, onChange) {
    const picker = document.getElementById(pickerId);
    const hex = document.getElementById(hexId);
    if (!picker || !hex) {
        return;
    }

    picker.addEventListener('input', () => {
        hex.value = picker.value.toLowerCase();
        onChange();
    });

    hex.addEventListener('input', () => {
        const val = hex.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
            picker.value = val.toLowerCase();
            onChange();
        } else if (/^[0-9a-fA-F]{6}$/.test(val)) {
            picker.value = `#${val.toLowerCase()}`;
            onChange();
        }
    });
}

function setupColorControls() {
    bindColorPair('fg-color-picker', 'fg-color-hex', () => schedulePreview());
    bindColorPair('fg-grad-picker', 'fg-grad-hex', () => schedulePreview());
    bindColorPair('finder-outer-picker', 'finder-outer-hex', () => schedulePreview());
    bindColorPair('finder-inner-picker', 'finder-inner-hex', () => schedulePreview());
    bindColorPair('bg-color-picker', 'bg-color-hex', () => schedulePreview());
    bindColorPair('bg-grad-picker', 'bg-grad-hex', () => schedulePreview());

    document.querySelectorAll('input[name="foreground_mode"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            syncVisibilityStates();
            schedulePreview();
        });
    });

    document.querySelectorAll('input[name="background_mode"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            syncVisibilityStates();
            schedulePreview();
        });
    });

    const angleSlider = document.getElementById('gradient-angle');
    const angleVal = document.getElementById('gradient-angle-val');
    if (angleSlider && angleVal) {
        angleSlider.addEventListener('input', () => {
            angleVal.textContent = `${angleSlider.value}°`;
            schedulePreview();
        });
    }

    const eyeToggle = document.getElementById('custom-eye-colors-toggle');
    if (eyeToggle) {
        eyeToggle.addEventListener('change', () => {
            syncVisibilityStates();
            schedulePreview();
        });
    }

    document.getElementById('random-palette-btn')?.addEventListener('click', () => {
        const pick = SAFE_RANDOM_PALETTES[Math.floor(Math.random() * SAFE_RANDOM_PALETTES.length)];
        setRadioValue('foreground_mode', 'linear');
        setColorPair('fg-color-picker', 'fg-color-hex', pick.fg1);
        setColorPair('fg-grad-picker', 'fg-grad-hex', pick.fg2);
        setRadioValue('background_mode', 'solid');
        setColorPair('bg-color-picker', 'bg-color-hex', pick.bg);

        const customEye = document.getElementById('custom-eye-colors-toggle');
        if (customEye) {
            customEye.checked = true;
        }
        setColorPair('finder-outer-picker', 'finder-outer-hex', pick.eyeOut);
        setColorPair('finder-inner-picker', 'finder-inner-hex', pick.eyeIn);

        syncVisibilityStates();
        schedulePreview(40);
        notify('Applied high-contrast color palette.');
    });
}

function setupLogoControls() {
    const fileInput = document.getElementById('logo-file-input');
    const removeBtn = document.getElementById('remove-logo-btn');
    const statusText = document.getElementById('logo-status-text');

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files?.[0];
            if (!file) {
                return;
            }
            if (file.type.includes('svg')) {
                notify('SVG logos are rejected for security and print consistency. Please choose PNG, JPEG, or WebP.', 'error');
                fileInput.value = '';
                return;
            }
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
                notify('Unsupported logo format. Choose a PNG, JPEG, or WebP image.', 'error');
                fileInput.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                notify('Logo exceeds maximum 2 MB limit.', 'error');
                fileInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = () => {
                state.logoDataUri = String(reader.result || '');
                removeBtn?.classList.remove('hidden');
                if (statusText) {
                    statusText.textContent = `Active logo: ${file.name} (${Math.round(file.size / 1024)} KB)`;
                }

                // Automatically recommend & switch to Error Correction H when adding a logo
                const eccSelect = document.getElementById('ecc-select');
                if (eccSelect && eccSelect.value !== 'H') {
                    eccSelect.value = 'H';
                    notify('Switched Error Correction to Level H (30%) for reliable logo scanning.');
                }
                schedulePreview(40);
            };
            reader.readAsDataURL(file);
        });
    }

    removeBtn?.addEventListener('click', () => {
        state.logoDataUri = null;
        if (fileInput) {
            fileInput.value = '';
        }
        removeBtn.classList.add('hidden');
        if (statusText) {
            statusText.textContent = 'Logo removed. Upload PNG, JPEG, or WebP up to 2 MB.';
        }
        schedulePreview(40);
    });

    const scaleSlider = document.getElementById('logo-scale');
    const scaleVal = document.getElementById('logo-scale-val');
    scaleSlider?.addEventListener('input', () => {
        if (scaleVal) {
            scaleVal.textContent = `${scaleSlider.value}%`;
        }
        schedulePreview();
    });

    const padSlider = document.getElementById('logo-padding');
    const padVal = document.getElementById('logo-padding-val');
    padSlider?.addEventListener('input', () => {
        if (padVal) {
            padVal.textContent = `${padSlider.value} px`;
        }
        schedulePreview();
    });

    document.getElementById('logo-bg-enabled')?.addEventListener('change', () => schedulePreview());
    document.getElementById('logo-bg-shape')?.addEventListener('change', () => schedulePreview());
    document.getElementById('logo-bg-color')?.addEventListener('input', () => schedulePreview());
}

function setupDesignControls() {
    document.querySelectorAll('input[name="module_shape"], input[name="finder_outer_shape"], input[name="finder_inner_shape"]').forEach((el) => {
        el.addEventListener('change', () => schedulePreview(50));
    });

    document.querySelectorAll('#builtin-presets-grid [data-preset]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-preset') || 'rounded';
            const preset = DESIGN_PRESETS[key];
            if (!preset) {
                return;
            }
            document.querySelectorAll('#builtin-presets-grid [data-preset]').forEach((b) => {
                b.classList.toggle('preset-btn-active', b === btn);
            });
            applyDesignObject(preset);
            schedulePreview(40);
            notify(`Applied "${btn.textContent?.trim()}" preset.`);
        });
    });

    ['ecc-select', 'version-select', 'mask-select', 'quiet-zone-input'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => schedulePreview(60));
    });
}

function setupResolutionAndExports() {
    const presetSelect = document.getElementById('size-preset-select');
    const customInput = document.getElementById('custom-size-input');
    const readout = document.getElementById('size-readout');
    const infoDims = document.getElementById('info-dimensions');

    const updateSizeLabels = (px) => {
        if (readout) {
            readout.textContent = `${px} × ${px} px`;
        }
        if (infoDims) {
            infoDims.textContent = `${px}×${px} px`;
        }
    };

    presetSelect?.addEventListener('change', () => {
        if (presetSelect.value !== 'custom' && customInput) {
            customInput.value = presetSelect.value;
            updateSizeLabels(Number(customInput.value));
            schedulePreview(80);
        } else {
            customInput?.focus();
        }
    });

    customInput?.addEventListener('input', () => {
        const val = Math.max(128, Math.min(2048, Number(customInput.value) || 1000));
        if (presetSelect) {
            const matching = Array.from(presetSelect.options).some((o) => o.value === String(val));
            presetSelect.value = matching ? String(val) : 'custom';
        }
        updateSizeLabels(val);
    });

    document.getElementById('export-filename-input')?.addEventListener('input', (e) => {
        e.target.dataset.userEdited = '1';
    });

    document.getElementById('generate-qr-btn')?.addEventListener('click', () => {
        fetchLivePreview();
    });

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            fetchLivePreview();
        }
    });

    document.querySelectorAll('[data-export-format]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const fmt = btn.getAttribute('data-export-format') || 'png';
            downloadExportFormat(fmt, btn);
        });
    });

    document.getElementById('copy-payload-btn')?.addEventListener('click', async () => {
        if (!state.lastEncodedPayload) {
            return;
        }
        try {
            await navigator.clipboard.writeText(state.lastEncodedPayload);
            notify('Copied encoded QR payload to clipboard.');
        } catch {
            notify('Clipboard access unavailable in this browser context.', 'warning');
        }
    });

    document.getElementById('copy-image-btn')?.addEventListener('click', async () => {
        if (!navigator.clipboard || typeof window.ClipboardItem === 'undefined') {
            notify('Direct PNG clipboard copy is not supported in this browser — use Download PNG instead.', 'warning');
            return;
        }
        try {
            const res = await fetch(`${BASE_PATH}/api/v1/qr/export`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ ...collectRequestPayload(), format: 'png' }),
            });
            if (!res.ok) {
                throw new Error('Failed to generate PNG for clipboard.');
            }
            const blob = await res.blob();
            await navigator.clipboard.write([new window.ClipboardItem({ 'image/png': blob })]);
            notify('Copied high-resolution PNG QR image to clipboard!');
        } catch (err) {
            notify(err instanceof Error ? err.message : 'Unable to copy image to clipboard.', 'error');
        }
    });
}

function setupDynamicQrControls() {
    const createBtn = document.getElementById('create-dynamic-btn');
    const credsPanel = document.getElementById('dynamic-credentials-panel');
    const shortInput = document.getElementById('dyn-short-url-display');
    const tokenInput = document.getElementById('dyn-owner-token-display');
    const copyTokenBtn = document.getElementById('copy-dyn-token-btn');
    const manageLink = document.getElementById('open-dyn-manage-link');
    const exitBtn = document.getElementById('exit-dynamic-btn');

    createBtn?.addEventListener('click', async () => {
        const currentUrl = (state.formValuesByType.url?.url || '').trim();
        if (!currentUrl) {
            notify('Enter a destination Website URL first.', 'warning');
            return;
        }

        createBtn.disabled = true;
        try {
            const res = await fetch(`${BASE_PATH}/api/v1/links`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ destination_url: currentUrl.startsWith('http') ? currentUrl : `https://${currentUrl}` }),
            });
            const payload = await res.json();
            if (!res.ok || !payload.ok) {
                throw new Error(payload?.error?.message || 'Could not create dynamic QR link.');
            }

            state.dynamicLink = payload.data;
            if (shortInput) {
                shortInput.value = payload.data.short_url;
            }
            if (tokenInput) {
                tokenInput.value = payload.data.ownership_token;
            }
            if (manageLink) {
                manageLink.href = payload.data.management_url;
            }
            credsPanel?.classList.remove('hidden');
            notify('Dynamic QR created! Copy and save your ownership token now.');
            fetchLivePreview();
        } catch (err) {
            notify(err instanceof Error ? err.message : 'Failed to create dynamic link.', 'error');
        } finally {
            createBtn.disabled = false;
        }
    });

    copyTokenBtn?.addEventListener('click', async () => {
        if (!tokenInput?.value) {
            return;
        }
        try {
            await navigator.clipboard.writeText(tokenInput.value);
            notify('Ownership secret copied to clipboard!');
        } catch {
            tokenInput.select();
        }
    });

    exitBtn?.addEventListener('click', () => {
        state.dynamicLink = null;
        credsPanel?.classList.add('hidden');
        fetchLivePreview();
        notify('Switched back to stateless Static QR mode.');
    });
}

function setupLocalPresetsAndJson() {
    document.getElementById('save-local-preset-btn')?.addEventListener('click', () => {
        const name = window.prompt('Enter a name for this design preset (only colors & shapes are saved locally):', 'My Brand Style');
        if (!name || !name.trim()) {
            return;
        }
        const presets = loadSavedPresets();
        presets.push({
            id: Date.now().toString(36),
            name: name.trim().slice(0, 32),
            design: collectDesignOptions(false),
        });
        try {
            localStorage.setItem(PRESETS_STORAGE_KEY, JSON.stringify(presets.slice(-12)));
            renderSavedPresets();
            notify(`Saved preset "${name.trim()}" locally.`);
        } catch {
            notify('Local storage is disabled in your browser.', 'warning');
        }
    });

    document.getElementById('export-json-btn')?.addEventListener('click', () => {
        const exported = {
            schema: 'qubiq-design-v1',
            exported_at: new Date().toISOString(),
            design: collectDesignOptions(false),
        };
        const blob = new Blob([JSON.stringify(exported, null, 2)], { type: 'application/json' });
        triggerBlobDownload(blob, 'qubiq-design-preset.json');
        notify('Exported design configuration as JSON.');
    });

    const importInput = document.getElementById('import-json-input');
    importInput?.addEventListener('change', () => {
        const file = importInput.files?.[0];
        if (!file) {
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            try {
                const parsed = JSON.parse(String(reader.result || '{}'));
                const designObj = parsed.design && typeof parsed.design === 'object' ? parsed.design : parsed;
                applyDesignObject(designObj);
                schedulePreview(40);
                notify('Imported design configuration!');
            } catch {
                notify('Invalid JSON preset file.', 'error');
            } finally {
                importInput.value = '';
            }
        };
        reader.readAsText(file);
    });

    document.getElementById('reset-design-btn')?.addEventListener('click', () => {
        state.logoDataUri = null;
        state.dynamicLink = null;
        document.getElementById('dynamic-credentials-panel')?.classList.add('hidden');
        document.getElementById('remove-logo-btn')?.classList.add('hidden');
        const logoInput = document.getElementById('logo-file-input');
        if (logoInput) {
            logoInput.value = '';
        }

        applyDesignObject({
            ...DESIGN_PRESETS.rounded,
            ecc: 'M',
            version: 'auto',
            mask_pattern: 'auto',
            quiet_zone: 4,
            size: 1000,
        });
        schedulePreview(40);
        notify('Reset QR design settings to defaults.');
    });
}

function loadSavedPresets() {
    try {
        const raw = localStorage.getItem(PRESETS_STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function renderSavedPresets() {
    const container = document.getElementById('saved-presets-list');
    if (!container) {
        return;
    }
    const presets = loadSavedPresets();
    container.innerHTML = '';

    if (presets.length === 0) {
        const empty = document.createElement('span');
        empty.className = 'text-[var(--text-muted)] italic';
        empty.textContent = 'No custom presets saved in this browser yet.';
        container.appendChild(empty);
        return;
    }

    for (const item of presets) {
        const pill = document.createElement('div');
        pill.className = 'inline-flex items-center rounded-xl border border-[var(--border-subtle)] bg-[var(--bg-page)] overflow-hidden';

        const loadBtn = document.createElement('button');
        loadBtn.type = 'button';
        loadBtn.className = 'px-2.5 py-1 hover:bg-emerald-500/10 font-medium';
        loadBtn.textContent = item.name;
        loadBtn.addEventListener('click', () => {
            applyDesignObject(item.design || {});
            schedulePreview(40);
            notify(`Loaded preset "${item.name}".`);
        });

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'px-2 py-1 text-rose-500 hover:bg-rose-500/10 border-l border-[var(--border-subtle)]';
        delBtn.setAttribute('aria-label', `Delete preset ${item.name}`);
        delBtn.textContent = '×';
        delBtn.addEventListener('click', () => {
            const next = loadSavedPresets().filter((p) => p.id !== item.id);
            try {
                localStorage.setItem(PRESETS_STORAGE_KEY, JSON.stringify(next));
            } catch {
                // Ignore
            }
            renderSavedPresets();
        });

        pill.append(loadBtn, delBtn);
        container.appendChild(pill);
    }
}

function syncVisibilityStates() {
    const fgMode = getRadioValue('foreground_mode', 'linear');
    document.getElementById('fg-gradient-end-wrap')?.classList.toggle('hidden', fgMode === 'solid');
    document.getElementById('fg-gradient-angle-wrap')?.classList.toggle('hidden', fgMode !== 'linear');

    const customEye = Boolean(document.getElementById('custom-eye-colors-toggle')?.checked);
    document.getElementById('custom-eye-colors-grid')?.classList.toggle('hidden', !customEye);

    const bgMode = getRadioValue('background_mode', 'solid');
    document.getElementById('bg-colors-grid')?.classList.toggle('hidden', bgMode === 'transparent');
    document.getElementById('bg-grad-end-wrap')?.classList.toggle('hidden', bgMode !== 'linear' && bgMode !== 'radial');

    const previewFrame = document.getElementById('qr-preview-frame');
    if (previewFrame) {
        previewFrame.classList.toggle('checkerboard-canvas', bgMode === 'transparent');
    }
}

function collectDesignOptions(includeLogo = true) {
    const customEye = Boolean(document.getElementById('custom-eye-colors-toggle')?.checked);
    const rawSize = Number(document.getElementById('custom-size-input')?.value || 1000);
    const clampedSize = Math.max(128, Math.min(2048, Number.isFinite(rawSize) ? rawSize : 1000));

    return {
        ecc: document.getElementById('ecc-select')?.value || 'M',
        version: document.getElementById('version-select')?.value || 'auto',
        mask_pattern: document.getElementById('mask-select')?.value || 'auto',
        quiet_zone: Number(document.getElementById('quiet-zone-input')?.value ?? 4),
        size: clampedSize,
        foreground_mode: getRadioValue('foreground_mode', 'linear'),
        foreground_color: document.getElementById('fg-color-hex')?.value || '#0f172a',
        foreground_gradient_end: document.getElementById('fg-grad-hex')?.value || '#059669',
        gradient_angle: Number(document.getElementById('gradient-angle')?.value || 135),
        finder_outer_color: customEye ? (document.getElementById('finder-outer-hex')?.value || '#0f172a') : null,
        finder_inner_color: customEye ? (document.getElementById('finder-inner-hex')?.value || '#059669') : null,
        background_mode: getRadioValue('background_mode', 'solid'),
        background_color: document.getElementById('bg-color-hex')?.value || '#ffffff',
        background_gradient_end: document.getElementById('bg-grad-hex')?.value || '#f1f5f9',
        module_shape: getRadioValue('module_shape', 'rounded'),
        finder_outer_shape: getRadioValue('finder_outer_shape', 'rounded'),
        finder_inner_shape: getRadioValue('finder_inner_shape', 'rounded'),
        logo_data: includeLogo ? state.logoDataUri : null,
        logo_scale: Number(document.getElementById('logo-scale')?.value || 20) / 100,
        logo_padding: Number(document.getElementById('logo-padding')?.value ?? 8),
        logo_background_enabled: Boolean(document.getElementById('logo-bg-enabled')?.checked),
        logo_background_shape: document.getElementById('logo-bg-shape')?.value || 'rounded',
        logo_background_color: document.getElementById('logo-bg-color')?.value || '#ffffff',
    };
}

function applyDesignObject(d) {
    if (!d || typeof d !== 'object') {
        return;
    }
    if (d.foreground_mode) setRadioValue('foreground_mode', String(d.foreground_mode));
    if (d.foreground_color) setColorPair('fg-color-picker', 'fg-color-hex', String(d.foreground_color));
    if (d.foreground_gradient_end) setColorPair('fg-grad-picker', 'fg-grad-hex', String(d.foreground_gradient_end));
    if (d.gradient_angle !== undefined) {
        const s = document.getElementById('gradient-angle');
        const v = document.getElementById('gradient-angle-val');
        if (s) s.value = String(d.gradient_angle);
        if (v) v.textContent = `${d.gradient_angle}°`;
    }

    const eyeToggle = document.getElementById('custom-eye-colors-toggle');
    const hasCustomEyes = Boolean(d.finder_outer_color || d.finder_inner_color);
    if (eyeToggle) {
        eyeToggle.checked = hasCustomEyes;
    }
    if (d.finder_outer_color) setColorPair('finder-outer-picker', 'finder-outer-hex', String(d.finder_outer_color));
    if (d.finder_inner_color) setColorPair('finder-inner-picker', 'finder-inner-hex', String(d.finder_inner_color));

    if (d.background_mode) setRadioValue('background_mode', String(d.background_mode));
    if (d.background_color) setColorPair('bg-color-picker', 'bg-color-hex', String(d.background_color));
    if (d.background_gradient_end) setColorPair('bg-grad-picker', 'bg-grad-hex', String(d.background_gradient_end));

    if (d.module_shape) setRadioValue('module_shape', String(d.module_shape));
    if (d.finder_outer_shape) setRadioValue('finder_outer_shape', String(d.finder_outer_shape));
    if (d.finder_inner_shape) setRadioValue('finder_inner_shape', String(d.finder_inner_shape));

    if (d.ecc) {
        const el = document.getElementById('ecc-select');
        if (el) el.value = String(d.ecc);
    }
    if (d.version !== undefined) {
        const el = document.getElementById('version-select');
        if (el) el.value = String(d.version);
    }
    if (d.mask_pattern !== undefined) {
        const el = document.getElementById('mask-select');
        if (el) el.value = String(d.mask_pattern);
    }
    if (d.quiet_zone !== undefined) {
        const el = document.getElementById('quiet-zone-input');
        if (el) el.value = String(d.quiet_zone);
    }
    if (d.size !== undefined) {
        const customSize = document.getElementById('custom-size-input');
        if (customSize) customSize.value = String(d.size);
    }

    syncVisibilityStates();
}

function collectRequestPayload() {
    const filenameRaw = document.getElementById('export-filename-input')?.value?.trim() || '';
    const payload = {
        type: state.activeType,
        data: { ...(state.formValuesByType[state.activeType] || {}) },
        design: collectDesignOptions(true),
        filename: filenameRaw || `qubiq-${state.activeType}-qr`,
    };

    if (state.activeType === 'url' && state.dynamicLink?.short_url) {
        payload.dynamic_short_url = state.dynamicLink.short_url;
    }

    return payload;
}

function schedulePreview(delayMs = 220) {
    if (debounceTimer) {
        window.clearTimeout(debounceTimer);
    }
    debounceTimer = window.setTimeout(() => {
        fetchLivePreview();
    }, delayMs);
}

async function fetchLivePreview() {
    if (activeAbortController) {
        activeAbortController.abort();
    }
    const controller = new AbortController();
    activeAbortController = controller;

    const spinner = document.getElementById('qr-preview-spinner');
    const statusBadge = document.getElementById('preview-status-badge');
    const validationBox = document.getElementById('form-validation-box');

    spinner?.classList.remove('hidden');
    if (statusBadge) {
        statusBadge.textContent = 'Updating...';
    }

    try {
        const res = await fetch(`${BASE_PATH}/api/v1/qr/preview`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(collectRequestPayload()),
            signal: controller.signal,
        });

        const body = await res.json();
        if (!res.ok || !body.ok) {
            const fieldMsgs = body?.error?.details?.fields
                ? Object.values(body.error.details.fields).join(' ')
                : '';
            const message = fieldMsgs || body?.error?.message || 'Unable to generate preview.';
            if (validationBox) {
                validationBox.textContent = message;
                validationBox.classList.remove('hidden');
            }
            if (statusBadge) {
                statusBadge.textContent = 'Check Input';
            }
            return;
        }

        validationBox?.classList.add('hidden');
        const stage = document.getElementById('qr-preview-stage');
        if (stage && body.data?.svg) {
            stage.innerHTML = body.data.svg;
        }

        state.lastEncodedPayload = body.data?.encoded_payload || '';
        updateDiagnosticsAndWarnings(body.data?.meta || {});

        if (statusBadge) {
            statusBadge.textContent = state.dynamicLink ? 'Dynamic Active' : 'Ready';
        }
    } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') {
            return;
        }
        if (statusBadge) {
            statusBadge.textContent = 'Offline / Error';
        }
    } finally {
        if (activeAbortController === controller) {
            spinner?.classList.add('hidden');
        }
    }
}

function updateDiagnosticsAndWarnings(meta) {
    const setTxt = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = String(val);
    };

    setTxt('info-type', meta.content_type || state.activeType);
    setTxt('info-bytes', `${meta.byte_length ?? 0} B (${meta.char_length ?? 0} chars)`);
    setTxt('info-version', `v${meta.version ?? 1}`);
    setTxt('info-ecc', meta.ecc_level || 'M');
    setTxt('info-matrix', `${meta.symbol_modules ?? 21}×${meta.symbol_modules ?? 21}`);
    setTxt('info-dimensions', `${meta.output_size ?? 1000}×${meta.output_size ?? 1000} px`);

    const warningsEl = document.getElementById('scan-warnings-container');
    if (!warningsEl) {
        return;
    }

    const warnings = Array.isArray(meta.warnings) ? meta.warnings : [];
    warningsEl.innerHTML = '';
    warningsEl.classList.toggle('hidden', warnings.length === 0);

    for (const w of warnings) {
        const div = document.createElement('div');
        const tone = w.severity === 'high'
            ? 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300'
            : w.severity === 'info'
                ? 'bg-sky-500/10 border-sky-500/30 text-sky-700 dark:text-sky-300'
                : 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300';
        div.className = `p-2.5 rounded-xl border text-[11px] leading-snug font-medium ${tone}`;
        div.textContent = w.message;
        warningsEl.appendChild(div);
    }
}

async function downloadExportFormat(format, buttonEl) {
    const originalText = buttonEl.textContent;
    buttonEl.disabled = true;
    buttonEl.textContent = 'Exporting...';

    try {
        const res = await fetch(`${BASE_PATH}/api/v1/qr/export`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ ...collectRequestPayload(), format }),
        });

        if (!res.ok) {
            let errMsg = `Failed to export ${format.toUpperCase()}.`;
            try {
                const errJson = await res.json();
                errMsg = errJson?.error?.message || errMsg;
            } catch {
                // Ignore non-JSON error
            }
            throw new Error(errMsg);
        }

        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="([^"]+)"/i);
        const filename = match ? match[1] : `qubiq-qr.${format === 'jpeg' ? 'jpg' : format}`;
        const blob = await res.blob();

        triggerBlobDownload(blob, filename);
        notify(`Downloaded ${filename} (${Math.max(1, Math.round(blob.size / 1024))} KB).`);
    } catch (err) {
        notify(err instanceof Error ? err.message : 'Export failed.', 'error');
    } finally {
        buttonEl.disabled = false;
        buttonEl.textContent = originalText;
    }
}

function triggerBlobDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1500);
}

function getRadioValue(name, fallback) {
    const checked = document.querySelector(`input[name="${name}"]:checked`);
    return checked ? checked.value : fallback;
}

function setRadioValue(name, value) {
    document.querySelectorAll(`input[name="${name}"]`).forEach((el) => {
        el.checked = el.value === value;
    });
}

function setColorPair(pickerId, hexId, hexValue) {
    const picker = document.getElementById(pickerId);
    const hex = document.getElementById(hexId);
    if (picker && /^#[0-9a-fA-F]{6}$/.test(hexValue)) {
        picker.value = hexValue.toLowerCase();
    }
    if (hex) {
        hex.value = hexValue.toLowerCase();
    }
}

document.addEventListener('DOMContentLoaded', init);

