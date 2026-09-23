// portal-dashboard.js — Auto-generated Module
export function init(ctx) {
    const { api, h, toast, badge, buildTable, buildPagination } = ctx;
    const view = document.getElementById('view');
    let currentPage = 1;

    loadData();

    async function loadData(page = 1) {
        currentPage = page;
        view.replaceChildren(h('div', { class: 'loading-overlay' }, [h('div', { class: 'spinner' })]));

        const endpoint = /api/v1/admin/portal-dashboard.replace('-dashboard', '/dashboard');
        const res = await api('GET', endpoint + ?page= + page + &limit=20);
        
        if (!res || !res.data.success) { 
            view.replaceChildren(h('div', { class: 'empty-state' }, [h('p', { text: 'No portal-dashboard found or endpoint pending.' })]));
            return; 
        }

        const items = res.data.data || [];
        const meta = res.data.meta || {};

        const columns = [
            { key: 'id', label: 'ID' },
            { key: 'name', label: 'Name / Ref' },
            { key: 'created_at', label: 'Created', render: v => v ? new Date(v).toLocaleDateString() : '' },
        ];

        const table = buildTable(columns, items);
        const pagination = buildPagination(page, meta.total_pages || 1, loadData);
        view.replaceChildren(table, pagination);
    }
}
