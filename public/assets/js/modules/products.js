// products.js — Products CRUD module
export function init(ctx) {
    const { api, h, toast, badge, buildTable, buildPagination, buildForm, getFormData, openModal, closeModal } = ctx;
    const view = document.getElementById('view');
    const actions = document.getElementById('page-actions');
    let currentPage = 1;

    if (actions) {
        actions.replaceChildren(
            h('button', { class: 'btn btn-primary', onClick: () => showCreateModal() }, [
                h('span', { text: '+ New Product' }),
            ])
        );
    }

    loadProducts();

    async function loadProducts(page = 1) {
        currentPage = page;
        view.replaceChildren(h('div', { class: 'loading-overlay' }, [h('div', { class: 'spinner' })]));

        const res = await api('GET', `/api/v1/admin/products?page=${page}&limit=20`);
        if (!res || !res.data.success) { toast('Failed to load products', 'error'); return; }

        const products = res.data.data || [];
        const meta = res.data.meta || {};

        const columns = [
            { key: 'sku', label: 'SKU' },
            { key: 'name', label: 'Name' },
            { key: 'category_name', label: 'Category' },
            { key: 'mrp', label: 'MRP (₹)' },
            { key: 'status', label: 'Status', render: v => badge(v || 'ACTIVE', statusType(v)) },
        ];

        const table = buildTable(columns, products);
        const pagination = buildPagination(page, meta.total_pages || 1, loadProducts);
        view.replaceChildren(table, pagination);
    }

    function showCreateModal() {
        toast('Product creation form not fully configured yet', 'info');
    }

    function statusType(s) {
        const m = { ACTIVE: 'success', INACTIVE: 'danger', ARCHIVED: 'neutral' };
        return m[(s || '').toUpperCase()] || 'neutral';
    }
}
