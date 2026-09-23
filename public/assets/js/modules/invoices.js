// invoices.js — Invoices Module
export function init(ctx) {
    const { api, h, toast, badge, buildTable, buildPagination } = ctx;
    const view = document.getElementById('view');
    let currentPage = 1;

    loadInvoices();

    async function loadInvoices(page = 1) {
        currentPage = page;
        view.replaceChildren(h('div', { class: 'loading-overlay' }, [h('div', { class: 'spinner' })]));

        const res = await api('GET', `/api/v1/admin/invoices?page=${page}&limit=20`);
        if (!res || !res.data.success) { toast('Failed to load invoices', 'error'); return; }

        const items = res.data.data || [];
        const meta = res.data.meta || {};

        const columns = [
            { key: 'invoice_no', label: 'Invoice No' },
            { key: 'order_ref', label: 'Order Ref' },
            { key: 'amount', label: 'Amount (₹)' },
            { key: 'status', label: 'Status', render: v => badge(v || 'UNPAID', statusType(v)) },
            { key: 'due_date', label: 'Due Date', render: v => v ? new Date(v).toLocaleDateString() : '' },
        ];

        const table = buildTable(columns, items);
        const pagination = buildPagination(page, meta.total_pages || 1, loadInvoices);
        view.replaceChildren(table, pagination);
    }

    function statusType(s) {
        const m = { UNPAID: 'warning', PARTIAL: 'info', PAID: 'success', OVERDUE: 'danger' };
        return m[(s || '').toUpperCase()] || 'neutral';
    }
}
