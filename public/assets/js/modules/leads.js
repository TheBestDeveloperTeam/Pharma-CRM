// leads.js — Leads CRUD module
export function init(ctx) {
    const { api, h, toast, badge, buildTable, buildPagination, buildForm, getFormData, openModal, closeModal } = ctx;
    const view = document.getElementById('view');
    const actions = document.getElementById('page-actions');
    let currentPage = 1;

    if (actions) {
        actions.replaceChildren(
            h('button', { class: 'btn btn-primary', onClick: () => showCreateModal() }, [
                h('span', { text: '+ New Lead' }),
            ])
        );
    }

    loadLeads();

    async function loadLeads(page = 1) {
        currentPage = page;
        view.replaceChildren(h('div', { class: 'loading-overlay' }, [h('div', { class: 'spinner' })]));

        const res = await api('GET', `/admin/leads?page=${page}&limit=20`);
        if (!res || !res.data.success) { toast('Failed to load leads', 'error'); return; }

        const leads = res.data.data || [];
        const meta = res.data.meta || {};

        const columns = [
            { key: 'lead_ref', label: 'Ref' },
            { key: 'company_name', label: 'Company' },
            { key: 'contact_name', label: 'Contact' },
            { key: 'mobile', label: 'Mobile' },
            { key: 'source', label: 'Source', render: v => badge(v || 'MANUAL', 'neutral') },
            { key: 'status', label: 'Status', render: v => badge(v || 'NEW', statusType(v)) },
            { key: 'assigned_to_name', label: 'Assigned To' },
            { key: 'created_at', label: 'Created', render: v => v ? new Date(v).toLocaleDateString('en-IN') : '' },
            { key: '_actions', label: 'Actions', render: (_, row) => {
                return h('div', { class: 'dt-actions' }, [
                    h('button', { class: 'btn btn-ghost btn-sm', text: 'View', onClick: () => viewLead(row.lead_ref) }),
                ]);
            }},
        ];

        const table = buildTable(columns, leads);
        const pagination = buildPagination(page, meta.total_pages || 1, loadLeads);
        view.replaceChildren(table, pagination);
    }

    function showCreateModal() {
        const form = buildForm([
            { name: 'company_name', label: 'Company Name', required: true },
            { name: 'contact_name', label: 'Contact Person', required: true },
            { name: 'mobile', label: 'Mobile Number', required: true },
            { name: 'email', label: 'Email', type: 'email' },
            { name: 'city', label: 'City' },
            { name: 'state', label: 'State' },
            { name: 'pincode', label: 'Pincode' },
            { name: 'source', label: 'Source', type: 'select', options: [
                { value: 'MANUAL', label: 'Manual Entry' },
                { value: 'WEBSITE', label: 'Website' },
                { value: 'REFERRAL', label: 'Referral' },
                { value: 'INDIAMART', label: 'IndiaMart' },
                { value: 'JUSTDIAL', label: 'JustDial' },
            ]},
            { name: 'notes', label: 'Notes', type: 'textarea' },
        ]);

        openModal('Create New Lead', form, {
            confirmText: 'Create Lead',
            size: 'lg',
            onConfirm: async (overlay) => {
                const data = getFormData(form);
                const res = await api('POST', '/admin/leads', data, { idempotencyKey: crypto.randomUUID() });
                if (res && res.data.success) {
                    toast('Lead created successfully', 'success');
                    closeModal(overlay);
                    loadLeads();
                } else {
                    toast(res?.data?.error?.message || 'Failed to create lead', 'error');
                }
            },
        });
    }

    async function viewLead(ref) {
        const res = await api('GET', `/admin/leads/${ref}`);
        if (!res || !res.data.success) { toast('Failed to load lead', 'error'); return; }
        const lead = res.data.data;

        const details = h('div', {}, [
            h('div', { class: 'form-row' }, [
                h('div', { class: 'form-group' }, [h('label', { class: 'form-label', text: 'Company' }), h('p', { text: lead.company_name || '-' })]),
                h('div', { class: 'form-group' }, [h('label', { class: 'form-label', text: 'Contact' }), h('p', { text: lead.contact_name || '-' })]),
            ]),
            h('div', { class: 'form-row' }, [
                h('div', { class: 'form-group' }, [h('label', { class: 'form-label', text: 'Mobile' }), h('p', { text: lead.mobile || '-' })]),
                h('div', { class: 'form-group' }, [h('label', { class: 'form-label', text: 'Email' }), h('p', { text: lead.email || '-' })]),
            ]),
            h('div', { class: 'form-row' }, [
                h('div', { class: 'form-group' }, [h('label', { class: 'form-label', text: 'Status' }), badge(lead.status || 'NEW', statusType(lead.status))]),
                h('div', { class: 'form-group' }, [h('label', { class: 'form-label', text: 'Source' }), h('p', { text: lead.source || '-' })]),
            ]),
            h('div', { class: 'form-group' }, [h('label', { class: 'form-label', text: 'Notes' }), h('p', { text: lead.notes || '-' })]),
        ]);

        openModal(`Lead: ${lead.lead_ref}`, details, { cancelText: 'Close', size: 'lg' });
    }

    function statusType(s) {
        const m = { NEW: 'info', ASSIGNED: 'primary', CONTACTED: 'primary', INTERESTED: 'warning', CONVERTED: 'success', REJECTED: 'danger', LOST: 'danger' };
        return m[(s || '').toUpperCase()] || 'neutral';
    }
}
