// parties.js — Parties CRUD module
export function init(ctx) {
    const { api, h, toast, badge, buildTable, buildPagination, buildForm, getFormData, openModal, closeModal } = ctx;
    const view = document.getElementById('view');
    const actions = document.getElementById('page-actions');
    let currentPage = 1;

    if (actions) {
        actions.replaceChildren(
            h('button', { class: 'btn btn-primary', onClick: () => showCreateModal() }, [
                h('span', { text: '+ New Party' }),
            ])
        );
    }

    loadParties();

    async function loadParties(page = 1) {
        currentPage = page;
        view.replaceChildren(h('div', { class: 'loading-overlay' }, [h('div', { class: 'spinner' })]));

        const res = await api('GET', `/api/v1/admin/parties?page=${page}&limit=20`);
        if (!res || !res.data.success) { toast('Failed to load parties', 'error'); return; }

        const parties = res.data.data || [];
        const meta = res.data.meta || {};

        const columns = [
            { key: 'party_ref', label: 'Ref' },
            { key: 'name', label: 'Name' },
            { key: 'mobile', label: 'Mobile' },
            { key: 'type', label: 'Type', render: v => badge(v || 'DISTRIBUTOR', 'info') },
            { key: 'status', label: 'Status', render: v => badge(v || 'ACTIVE', statusType(v)) },
            { key: '_actions', label: 'Actions', render: (_, row) => {
                return h('div', { class: 'dt-actions' }, [
                    h('button', { class: 'btn btn-ghost btn-sm', text: 'View', onClick: () => viewParty(row.party_ref) }),
                ]);
            }},
        ];

        const table = buildTable(columns, parties);
        const pagination = buildPagination(page, meta.total_pages || 1, loadParties);
        view.replaceChildren(table, pagination);
    }

    function showCreateModal() {
        const form = buildForm([
            { name: 'name', label: 'Party Name', required: true },
            { name: 'mobile', label: 'Mobile Number', required: true },
            { name: 'type', label: 'Type', type: 'select', options: [
                { value: 'DISTRIBUTOR', label: 'Distributor' },
                { value: 'STOCKIST', label: 'Stockist' },
                { value: 'RETAILER', label: 'Retailer' }
            ]},
        ]);

        openModal('Create New Party', form, {
            confirmText: 'Create Party',
            size: 'md',
            onConfirm: async (overlay) => {
                const data = getFormData(form);
                const res = await api('POST', '/api/v1/admin/parties', data);
                if (res && res.data.success) {
                    toast('Party created successfully', 'success');
                    closeModal(overlay);
                    loadParties();
                } else {
                    toast(res?.data?.error?.message || 'Failed to create party', 'error');
                }
            },
        });
    }

    async function viewParty(ref) {
        toast('View functionality to be implemented', 'info');
    }

    function statusType(s) {
        const m = { ACTIVE: 'success', INACTIVE: 'danger' };
        return m[(s || '').toUpperCase()] || 'neutral';
    }
}
