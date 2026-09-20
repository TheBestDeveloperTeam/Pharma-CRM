// datatable.js — Accessible, zero-dependency, safe DOM table renderer
// NO innerHTML — textContent and createElement only via h()

import { h, api } from '../crm-ui.js';

export class DataTable {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.endpoint = options.endpoint || '';
        this.columns = options.columns || [];
        this.page = 1;
        this.perPage = options.perPage || 25;
        this.filters = options.filters || {};
        this.sortColumn = options.defaultSort || '';
        this.sortDirection = options.defaultDirection || 'ASC';
    }

    async load() {
        if (!this.container) return;

        // Clear container safely
        while (this.container.firstChild) {
            this.container.removeChild(this.container.firstChild);
        }

        const queryParams = new URLSearchParams({
            page: this.page.toString(),
            per_page: this.perPage.toString(),
            ...this.filters
        });

        const res = await api('GET', `${this.endpoint}?${queryParams.toString()}`);
        if (!res || res.status !== 200 || !res.data.success) {
            const errEl = h('div', { class: 'card-body', text: 'Failed to load records.' });
            this.container.appendChild(errEl);
            return;
        }

        const items = res.data.data || [];
        const meta = res.data.meta || { page: 1, pages: 1, total: 0 };

        // Render Table
        const table = h('table', { class: 'table' });
        const thead = h('thead');
        const headerRow = h('tr');

        for (const col of this.columns) {
            const th = h('th', { text: col.label });
            headerRow.appendChild(th);
        }
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = h('tbody');
        if (items.length === 0) {
            const emptyRow = h('tr', {}, [
                h('td', { colspan: this.columns.length.toString(), text: 'No records found.' })
            ]);
            tbody.appendChild(emptyRow);
        } else {
            for (const item of items) {
                const tr = h('tr');
                for (const col of this.columns) {
                    const td = h('td');
                    if (typeof col.render === 'function') {
                        const rendered = col.render(item[col.key], item);
                        if (typeof rendered === 'string') {
                            td.textContent = rendered;
                        } else if (rendered instanceof Node) {
                            td.appendChild(rendered);
                        }
                    } else {
                        td.textContent = (item[col.key] !== null && item[col.key] !== undefined) ? String(item[col.key]) : '—';
                    }
                    tr.appendChild(td);
                }
                tbody.appendChild(tr);
            }
        }
        table.appendChild(tbody);
        this.container.appendChild(table);

        // Render Pagination controls
        if (meta.pages > 1) {
            const pag = h('div', { style: 'display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding: 0 0.5rem;' });
            const info = h('span', { text: `Page ${meta.page} of ${meta.pages} (${meta.total} total)` });

            const btnPrev = h('button', { class: 'btn btn-ghost btn-sm', text: 'Previous' });
            btnPrev.disabled = meta.page <= 1;
            btnPrev.onclick = () => { this.page--; this.load(); };

            const btnNext = h('button', { class: 'btn btn-ghost btn-sm', text: 'Next' });
            btnNext.disabled = meta.page >= meta.pages;
            btnNext.onclick = () => { this.page++; this.load(); };

            const btns = h('div', { style: 'display: flex; gap: 0.5rem;' }, [btnPrev, btnNext]);
            pag.appendChild(info);
            pag.appendChild(btns);
            this.container.appendChild(pag);
        }
    }
}
