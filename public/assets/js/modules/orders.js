// orders.js — Orders Module
export function init(ctx) {
    const { api, h, toast, badge, buildTable, buildPagination } = ctx;
    const view = document.getElementById('view');
    let currentPage = 1;

    loadOrders();

    async function loadOrders(page = 1) {
        currentPage = page;
        view.replaceChildren(h('div', { class: 'loading-overlay' }, [h('div', { class: 'spinner' })]));

        const res = await api('GET', `/api/v1/admin/orders?page=${page}&limit=20`);
        if (!res || !res.data.success) { toast('Failed to load orders', 'error'); return; }

        const orders = res.data.data || [];
        const meta = res.data.meta || {};

        const columns = [
            { key: 'order_ref', label: 'Order ID' },
            { key: 'party_name', label: 'Party' },
            { key: 'total_amount', label: 'Amount (₹)' },
            { key: 'status', label: 'Status', render: v => badge(v || 'DRAFT', statusType(v)) },
            { key: 'created_at', label: 'Date', render: v => v ? new Date(v).toLocaleDateString() : '' },
        ];

        const table = buildTable(columns, orders);
        const pagination = buildPagination(page, meta.total_pages || 1, loadOrders);
        view.replaceChildren(table, pagination);
    }

    function statusType(s) {
        const m = { DRAFT: 'neutral', INVOICED: 'info', DISPATCHED: 'primary', DELIVERED: 'success', CANCELLED: 'danger' };
        return m[(s || '').toUpperCase()] || 'neutral';
    }
}
