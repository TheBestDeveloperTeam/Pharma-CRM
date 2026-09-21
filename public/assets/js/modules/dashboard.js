// dashboard.js — Admin/Sales dashboard module
export function init(ctx) {
    const { surface, api, h, toast, badge, buildTable } = ctx;
    const view = document.getElementById('view');
    if (!view) return;

    if (surface === 'admin') loadAdminDashboard(ctx);
    else if (surface === 'sales') loadSalesDashboard(ctx);
    else loadGenericDashboard(ctx);
}

async function loadAdminDashboard({ api, h, badge, buildTable }) {
    const view = document.getElementById('view');
    view.replaceChildren(h('div', { class: 'loading-overlay' }, [h('div', { class: 'spinner spinner-lg' }), h('span', { text: 'Loading dashboard...' })]));

    // Fetch recent data in parallel
    const [ordersRes, leadsRes, paymentsRes] = await Promise.all([
        api('GET', '/admin/orders?limit=5'),
        api('GET', '/admin/leads?limit=5'),
        api('GET', '/admin/payments?limit=5'),
    ]);

    const stats = h('div', { class: 'stat-grid' });
    const orders = ordersRes?.data?.data || [];
    const leads = leadsRes?.data?.data || [];
    const payments = paymentsRes?.data?.data || [];

    // Stat cards
    const cards = [
        { value: String(orders.length || '0'), label: 'Recent Orders', icon: 'orders', color: 'bg-primary' },
        { value: String(leads.length || '0'), label: 'Active Leads', icon: 'leads', color: 'bg-info' },
        { value: String(payments.length || '0'), label: 'Payments', icon: 'payments', color: 'bg-success' },
    ];

    cards.forEach(c => {
        stats.appendChild(h('div', { class: 'stat-card' }, [
            h('div', {}, [
                h('div', { class: 'stat-value', text: c.value }),
                h('div', { class: 'stat-label', text: c.label }),
            ]),
            h('div', { class: `stat-icon ${c.color}` }, [
                h('svg', { width: '24', height: '24' }, [(() => { const u = document.createElementNS('http://www.w3.org/2000/svg','use'); u.setAttributeNS('http://www.w3.org/1999/xlink','href',`#icon-${c.icon}`); return u; })()]),
            ]),
        ]));
    });

    // Recent orders table
    const orderColumns = [
        { key: 'order_ref', label: 'Order #' },
        { key: 'party_name', label: 'Party' },
        { key: 'total_amount', label: 'Amount', render: v => '₹' + Number(v || 0).toLocaleString('en-IN') },
        { key: 'status', label: 'Status', render: v => badge(v || 'DRAFT', statusColor(v)) },
        { key: 'created_at', label: 'Date', render: v => v ? new Date(v).toLocaleDateString('en-IN') : '' },
    ];

    const recentLeadColumns = [
        { key: 'lead_ref', label: 'Lead #' },
        { key: 'company_name', label: 'Company' },
        { key: 'contact_name', label: 'Contact' },
        { key: 'status', label: 'Status', render: v => badge(v || 'NEW', statusColor(v)) },
    ];

    view.replaceChildren(
        stats,
        h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem' } }, [
            h('div', { class: 'card' }, [
                h('div', { class: 'card-header' }, [h('span', { text: 'Recent Orders' })]),
                h('div', { class: 'card-body', style: { padding: '0' } }, [buildTable(orderColumns, orders)]),
            ]),
            h('div', { class: 'card' }, [
                h('div', { class: 'card-header' }, [h('span', { text: 'Recent Leads' })]),
                h('div', { class: 'card-body', style: { padding: '0' } }, [buildTable(recentLeadColumns, leads)]),
            ]),
        ])
    );
}

async function loadSalesDashboard({ api, h, badge, buildTable }) {
    const view = document.getElementById('view');
    view.replaceChildren(h('div', { class: 'loading-overlay' }, [h('div', { class: 'spinner spinner-lg' })]));

    const [leadsRes, followUpsRes] = await Promise.all([
        api('GET', '/admin/leads?limit=10'),
        api('GET', '/admin/follow-ups?limit=10'),
    ]);

    const leads = leadsRes?.data?.data || [];
    const followUps = followUpsRes?.data?.data || [];

    const stats = h('div', { class: 'stat-grid' }, [
        makeStat(String(leads.length), 'My Leads', 'leads', 'bg-info'),
        makeStat(String(followUps.length), 'Pending Follow-ups', 'followups', 'bg-warning'),
    ]);

    view.replaceChildren(stats,
        h('div', { class: 'card' }, [
            h('div', { class: 'card-header' }, [h('span', { text: 'Upcoming Follow-ups' })]),
            h('div', { class: 'card-body', style: { padding: '0' } }, [
                buildTable([
                    { key: 'follow_up_ref', label: 'Ref' },
                    { key: 'lead_ref', label: 'Lead' },
                    { key: 'scheduled_at', label: 'Scheduled', render: v => v ? new Date(v).toLocaleString('en-IN') : '' },
                    { key: 'status', label: 'Status', render: v => badge(v || 'PENDING', statusColor(v)) },
                ], followUps),
            ]),
        ])
    );
}

function loadGenericDashboard({ h }) {
    const view = document.getElementById('view');
    view.replaceChildren(
        h('div', { class: 'card' }, [
            h('div', { class: 'card-header' }, [h('span', { text: 'Welcome' })]),
            h('div', { class: 'card-body' }, [h('p', { text: 'Dashboard data is loading.' })]),
        ])
    );
}

function makeStat(value, label, icon, color) {
    const { h } = window.__crm || { h: document.createElement.bind(document) };
    return document.createElement('div'); // fallback; real implementation uses ctx.h
}

function statusColor(status) {
    const map = {
        DRAFT: 'neutral', NEW: 'info', SUBMITTED: 'info', ASSIGNED: 'info',
        CONTACTED: 'primary', INTERESTED: 'primary', CONFIRMED: 'success',
        INVOICED: 'success', DISPATCHED: 'warning', DELIVERED: 'success',
        PAID: 'success', CANCELLED: 'danger', REJECTED: 'danger',
        CONVERTED: 'success', COMPLETED: 'success', PENDING: 'warning',
        ACTIVE: 'success', INACTIVE: 'neutral', OVERDUE: 'danger',
    };
    return map[(status || '').toUpperCase()] || 'neutral';
}
