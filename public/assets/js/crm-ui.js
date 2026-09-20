// crm-ui.js — Core application shell
// NO innerHTML anywhere — only textContent and createElement
// NO localStorage for tokens

// ── Token Manager ─────────────────────────────────────────────────────────
let _accessToken = null; // In-memory only

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
        else el.setAttribute(k, v);
    }
    for (const child of children) {
        if (typeof child === 'string') {
            el.appendChild(document.createTextNode(child));
        } else if (child instanceof Node) {
            el.appendChild(child);
        }
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

    const surface = document.documentElement.dataset.surface;
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

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="toggle-sidebar"]')) {
            document.querySelector('.main-sidebar')?.classList.toggle('sidebar-open');
        }
        if (e.target.closest('[data-action="logout"]')) {
            logout();
        }
    });
}

document.addEventListener('DOMContentLoaded', boot);
export { api, h, toast, tokens, logout };
