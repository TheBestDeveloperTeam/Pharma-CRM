// crm-ui.js — Core application shell
// NO innerHTML anywhere — only textContent and createElement
// NO localStorage for tokens (R13)

// ── Token Manager ─────────────────────────────────────────────────────────
let _accessToken = null; // In-memory only (R13)

const tokens = {
    getAccess: () => _accessToken,
    setAccess: (t) => { _accessToken = t; },
    getRefresh: () => sessionStorage.getItem('_rt'),
    setRefresh: (t) => sessionStorage.setItem('_rt', t),
    clear: () => {
        _accessToken = null;
        sessionStorage.removeItem('_rt');
    },
};

// ── API Helper ────────────────────────────────────────────────────────────
async function api(method, path, body = null, opts = {}) {
    const headers = {
        'Content-Type': 'application/json',
        'X-Request-ID': crypto.randomUUID(),
    };

    if (tokens.getAccess()) {
        headers['Authorization'] = 'Bearer ' + tokens.getAccess();
    }

    if (opts.idempotencyKey) {
        headers['Idempotency-Key'] = opts.idempotencyKey;
    }

    const res = await fetch('/api/v1' + path, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
    });

    // Auto-refresh on 401
    if (res.status === 401 && tokens.getRefresh() && !opts._retried && path !== '/oauth/token') {
        const refreshed = await refreshAccessToken();
        if (refreshed) {
            return api(method, path, body, { ...opts, _retried: true });
        }
        logout();
        return null;
    }

    let data = null;
    try {
        data = await res.json();
    } catch (e) {
        data = { success: false, error: { message: 'Invalid JSON response' } };
    }

    return { status: res.status, data };
}

async function refreshAccessToken() {
    const rt = tokens.getRefresh();
    if (!rt) return false;

    const surface = document.documentElement.dataset.surface || 'admin';

    const res = await fetch('/api/v1/oauth/token', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Surface': surface,
        },
        body: JSON.stringify({ grant_type: 'refresh_token', refresh_token: rt }),
    });

    if (!res.ok) {
        tokens.clear();
        return false;
    }

    const json = await res.json();
    if (json.data && json.data.access_token) {
        tokens.setAccess(json.data.access_token);
        tokens.setRefresh(json.data.refresh_token);
        return true;
    }
    return false;
}

// ── DOM Helper ────────────────────────────────────────────────────────────
function h(tag, attrs = {}, children = []) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'class') el.className = v;
        else if (k === 'text') el.textContent = v;
        else if (k === 'style' && typeof v === 'object') Object.assign(el.style, v);
        else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
        else if (k === 'dataset' && typeof v === 'object') Object.assign(el.dataset, v);
        else el.setAttribute(k, v);
    }
    for (const child of children) {
        if (typeof child === 'string') el.appendChild(document.createTextNode(child));
        else if (child instanceof Node) el.appendChild(child);
    }
    return el;
}

// ── Toast Notifications ───────────────────────────────────────────────────
function toast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toasts');
    if (!container) return;

    const toastEl = h('div', { class: `toast toast-${type}`, role: 'alert' }, [
        h('span', { text: message }),
    ]);

    container.appendChild(toastEl);
    setTimeout(() => toastEl.remove(), duration);
}

// ── Modal Controller ──────────────────────────────────────────────────────
function openModal(titleText, bodyContent, opts = {}) {
    const root = document.getElementById('modal-root');
    if (!root) return null;

    const overlay = h('div', { class: 'modal-overlay open', role: 'dialog', 'aria-modal': 'true' }, [
        h('div', { class: `modal-dialog ${opts.size === 'lg' ? 'modal-lg' : opts.size === 'xl' ? 'modal-xl' : ''}` }, [
            h('div', { class: 'modal-header' }, [
                h('h3', { class: 'modal-title', text: titleText }),
                h('button', { class: 'modal-close', 'aria-label': 'Close', text: '✕', onClick: () => closeModal(overlay) }),
            ]),
            h('div', { class: 'modal-body' }, Array.isArray(bodyContent) ? bodyContent : [bodyContent]),
            h('div', { class: 'modal-footer' }, [
                h('button', { class: 'btn btn-ghost', text: opts.cancelText || 'Cancel', onClick: () => closeModal(overlay) }),
                ...(opts.confirmText ? [h('button', {
                    class: `btn ${opts.confirmClass || 'btn-primary'}`,
                    text: opts.confirmText,
                    onClick: () => {
                        if (opts.onConfirm) opts.onConfirm(overlay);
                        else closeModal(overlay);
                    },
                })] : []),
            ]),
        ]),
    ]);

    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal(overlay);
    });

    // Close on Escape
    const escHandler = (e) => {
        if (e.key === 'Escape') { closeModal(overlay); document.removeEventListener('keydown', escHandler); }
    };
    document.addEventListener('keydown', escHandler);

    root.appendChild(overlay);
    return overlay;
}

function closeModal(overlay) {
    if (overlay && overlay.parentNode) {
        overlay.classList.remove('open');
        setTimeout(() => overlay.remove(), 200);
    }
}

// ── Confirm Dialog ────────────────────────────────────────────────────────
function confirm(title, message, opts = {}) {
    return new Promise((resolve) => {
        const body = h('div', { class: 'confirm-dialog' }, [
            h('div', { class: 'confirm-dialog-icon' }, [h('span', { text: '⚠', style: { fontSize: '1.5rem' } })]),
            h('div', { class: 'confirm-dialog-title', text: title }),
            h('p', { class: 'confirm-dialog-text', text: message }),
        ]);

        openModal(opts.modalTitle || 'Confirm Action', body, {
            confirmText: opts.confirmText || 'Confirm',
            confirmClass: opts.confirmClass || 'btn-danger',
            cancelText: opts.cancelText || 'Cancel',
            onConfirm: (overlay) => { closeModal(overlay); resolve(true); },
        });

        // If user closes without confirming
        setTimeout(() => resolve(false), 0);
    });
}

// ── Loading Helpers ───────────────────────────────────────────────────────
function showLoading(containerId = 'view') {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.replaceChildren(
        h('div', { class: 'loading-overlay' }, [
            h('div', { class: 'spinner spinner-lg' }),
            h('span', { text: 'Loading...' }),
        ])
    );
}

function hideLoading() {
    const loader = document.getElementById('page-loading');
    if (loader) loader.remove();
}

// ── Table Builder ─────────────────────────────────────────────────────────
function buildTable(columns, rows, opts = {}) {
    const table = h('table', { class: 'data-table' });
    const thead = h('thead');
    const headerRow = h('tr');
    columns.forEach(col => {
        headerRow.appendChild(h('th', { text: col.label || col.key }));
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = h('tbody');
    if (rows.length === 0) {
        const emptyRow = h('tr');
        emptyRow.appendChild(h('td', {
            colspan: String(columns.length),
            class: 'text-center text-muted',
            style: { padding: '2rem' },
            text: opts.emptyText || 'No records found',
        }));
        tbody.appendChild(emptyRow);
    } else {
        rows.forEach(row => {
            const tr = h('tr');
            columns.forEach(col => {
                const td = h('td');
                if (col.render) {
                    const rendered = col.render(row[col.key], row);
                    if (rendered instanceof Node) td.appendChild(rendered);
                    else td.textContent = String(rendered ?? '');
                } else {
                    td.textContent = String(row[col.key] ?? '');
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }
    table.appendChild(tbody);
    return h('div', { class: 'table-wrapper' }, [table]);
}

// ── Badge Builder ─────────────────────────────────────────────────────────
function badge(text, type = 'neutral') {
    return h('span', { class: `badge badge-${type}`, text });
}

// ── Pagination Builder ────────────────────────────────────────────────────
function buildPagination(page, totalPages, onChange) {
    const wrap = h('div', { class: 'pagination' });
    const info = h('span', { class: 'pagination-info', text: `Page ${page} of ${totalPages}` });
    const controls = h('div', { class: 'pagination-controls' });

    const prevBtn = h('button', { class: 'page-btn', text: '‹', onClick: () => onChange(page - 1) });
    if (page <= 1) prevBtn.disabled = true;
    controls.appendChild(prevBtn);

    const start = Math.max(1, page - 2);
    const end = Math.min(totalPages, page + 2);
    for (let i = start; i <= end; i++) {
        controls.appendChild(h('button', {
            class: `page-btn${i === page ? ' active' : ''}`,
            text: String(i),
            onClick: () => onChange(i),
        }));
    }

    const nextBtn = h('button', { class: 'page-btn', text: '›', onClick: () => onChange(page + 1) });
    if (page >= totalPages) nextBtn.disabled = true;
    controls.appendChild(nextBtn);

    wrap.appendChild(info);
    wrap.appendChild(controls);
    return wrap;
}

// ── Form Builder ──────────────────────────────────────────────────────────
function buildForm(fields, opts = {}) {
    const form = h('form', { class: opts.class || '' });
    form.addEventListener('submit', (e) => e.preventDefault());

    fields.forEach(f => {
        const group = h('div', { class: 'form-group' });
        const label = h('label', { class: 'form-label', for: `field-${f.name}` });
        label.textContent = f.label || f.name;
        if (f.required) label.appendChild(h('span', { class: 'required', text: ' *' }));
        group.appendChild(label);

        let input;
        if (f.type === 'select') {
            input = h('select', { class: 'form-control', id: `field-${f.name}`, name: f.name });
            input.appendChild(h('option', { value: '', text: f.placeholder || '— Select —' }));
            (f.options || []).forEach(opt => {
                const o = h('option', { value: opt.value, text: opt.label || opt.value });
                if (opt.value === f.value) o.selected = true;
                input.appendChild(o);
            });
        } else if (f.type === 'textarea') {
            input = h('textarea', { class: 'form-control', id: `field-${f.name}`, name: f.name, placeholder: f.placeholder || '' });
            input.textContent = f.value || '';
        } else {
            input = h('input', {
                class: 'form-control', type: f.type || 'text',
                id: `field-${f.name}`, name: f.name,
                value: f.value || '', placeholder: f.placeholder || '',
            });
        }
        if (f.required) input.required = true;
        group.appendChild(input);

        if (f.hint) group.appendChild(h('div', { class: 'form-hint', text: f.hint }));
        form.appendChild(group);
    });

    return form;
}

function getFormData(form) {
    const data = {};
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(el => {
        if (el.name) data[el.name] = el.value;
    });
    return data;
}

// ── Multi-tab sync ────────────────────────────────────────────────────────
let channel = null;
try {
    channel = new BroadcastChannel('crm-auth');
    channel.onmessage = (e) => {
        if (e.data === 'logout') {
            tokens.clear();
            window.location.href = window.CRM_LOGIN_URL || '/';
        }
    };
} catch (e) {}

function logout() {
    api('POST', '/oauth/revoke').finally(() => {
        tokens.clear();
        if (channel) channel.postMessage('logout');
        window.location.href = window.CRM_LOGIN_URL || '/';
    });
}

// ── Page Module Loader ────────────────────────────────────────────────────
const MODULE_MAP = {
    'dashboard':     '/assets/js/modules/dashboard.js',
    'leads':         '/assets/js/modules/leads.js',
    'follow-ups':    '/assets/js/modules/followups.js',
    'parties':       '/assets/js/modules/parties.js',
    'territories':   '/assets/js/modules/territories.js',
    'categories':    '/assets/js/modules/categories.js',
    'products':      '/assets/js/modules/products.js',
    'tiers':         '/assets/js/modules/tiers.js',
    'prices':        '/assets/js/modules/prices.js',
    'schemes':       '/assets/js/modules/schemes.js',
    'orders':        '/assets/js/modules/orders.js',
    'invoices':      '/assets/js/modules/invoices.js',
    'dispatches':    '/assets/js/modules/dispatches.js',
    'payments':      '/assets/js/modules/payments.js',
    'inventory':     '/assets/js/modules/inventory.js',
    'users':         '/assets/js/modules/users.js',
    'reports':       '/assets/js/modules/reports.js',
    'notifications': '/assets/js/modules/notifications.js',
    'settings':      '/assets/js/modules/settings.js',
    'catalogue':     '/assets/js/modules/portal-dashboard.js',
    'outstanding':   '/assets/js/modules/portal-dashboard.js',
    'profile':       '/assets/js/modules/portal-dashboard.js',
    'organizations': '/assets/js/modules/super-dashboard.js',
    'franchises':    '/assets/js/modules/super-dashboard.js',
    'audit':         '/assets/js/modules/super-dashboard.js',
    'security':      '/assets/js/modules/super-dashboard.js',
};

async function loadPageModule(page, surface) {
    const modulePath = MODULE_MAP[page];
    if (!modulePath) return;

    try {
        const mod = await import(modulePath);
        if (mod.init) {
            hideLoading();
            mod.init({ surface, page, api, h, toast, badge, buildTable, buildPagination, buildForm, getFormData, openModal, closeModal, confirm, showLoading });
        }
    } catch (err) {
        console.error('Failed to load page module:', err);
        hideLoading();
        const view = document.getElementById('view');
        if (view) {
            view.replaceChildren(
                h('div', { class: 'alert alert-warning' }, [
                    h('span', { text: `Module "${page}" is not yet available.` }),
                ])
            );
        }
    }
}

// ── Boot Application ──────────────────────────────────────────────────────
async function boot() {
    const app = document.getElementById('app');
    if (!app) return; // Not a dashboard shell

    // Attempt token refresh on page load
    const ok = await refreshAccessToken();
    if (!ok) {
        window.location.href = window.CRM_LOGIN_URL || '/';
        return;
    }

    // Verify current authenticated identity
    const res = await api('GET', '/auth/me');
    if (!res || res.status !== 200 || !res.data.success) {
        logout();
        return;
    }

    const surface = window.CRM_SURFACE || document.documentElement.dataset.surface;
    const roleMap = {
        super: 'SUPER_ADMIN',
        admin: 'FRANCHISE_ADMIN',
        sales: 'SALES',
        portal: 'DISTRIBUTOR'
    };

    if (res.data.data.role !== roleMap[surface]) {
        logout();
        return;
    }

    // Display app UI
    app.removeAttribute('hidden');
    const whoEl = document.getElementById('who');
    if (whoEl) {
        whoEl.textContent = res.data.data.name || res.data.data.email;
    }

    if (res.data.data.impersonator_ref) {
        const banner = document.getElementById('impersonation-banner');
        if (banner) {
            banner.textContent = `Impersonating ${res.data.data.name} (by Super Admin ${res.data.data.impersonator_ref})`;
            banner.style.display = 'block';
        }
    }

    // Event delegation
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="toggle-sidebar"]')) {
            document.querySelector('.main-sidebar')?.classList.toggle('sidebar-open');
        }
        if (e.target.closest('[data-action="logout"]')) {
            logout();
        }
        // Dismiss alerts
        const alertClose = e.target.closest('.alert-close');
        if (alertClose) {
            alertClose.closest('.alert')?.remove();
        }
    });

    // Load page module
    const page = window.CRM_PAGE || 'dashboard';
    await loadPageModule(page, surface);
}

document.addEventListener('DOMContentLoaded', boot);
export { api, h, toast, tokens, logout, badge, buildTable, buildPagination, buildForm, getFormData, openModal, closeModal, confirm, showLoading, hideLoading };
