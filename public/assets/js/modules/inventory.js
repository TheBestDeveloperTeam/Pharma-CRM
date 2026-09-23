// inventory.js — Inventory Module
export function init(ctx) {
    const { api, h, toast, badge, buildTable, buildPagination } = ctx;
    const view = document.getElementById('view');
    let currentPage = 1;

    loadInventory();

    async function loadInventory(page = 1) {
        currentPage = page;
        view.replaceChildren(h('div', { class: 'loading-overlay' }, [h('div', { class: 'spinner' })]));

        const res = await api('GET', `/api/v1/admin/inventory/near-expiry`); // Generic fetch
        if (!res || !res.data.success) { toast('Failed to load inventory', 'error'); return; }

        const items = res.data.data || [];
        
        const columns = [
            { key: 'product_name', label: 'Product' },
            { key: 'batch_no', label: 'Batch' },
            { key: 'qty_available', label: 'Available Qty' },
            { key: 'expiry_date', label: 'Expiry', render: v => v ? new Date(v).toLocaleDateString() : '' },
        ];

        const table = buildTable(columns, items);
        view.replaceChildren(table);
    }
}
