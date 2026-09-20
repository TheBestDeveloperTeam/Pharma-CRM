// formbuilder.js — Idempotent FormBuilder with strict DOM generation
// NO innerHTML — safe textContent and createElement only

import { h, api, toast } from '../crm-ui.js';

export class FormBuilder {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.endpoint = options.endpoint || '';
        this.method = options.method || 'POST';
        this.fields = options.fields || [];
        this.submitText = options.submitText || 'Save';
        this.onSuccess = options.onSuccess || (() => {});
    }

    render() {
        if (!this.container) return;

        while (this.container.firstChild) {
            this.container.removeChild(this.container.firstChild);
        }

        const form = h('form');
        const fieldInputs = {};

        for (const f of this.fields) {
            const group = h('div', { class: 'form-group', style: 'margin-bottom: 1rem;' });
            const label = h('label', { class: 'form-label', text: f.label });
            group.appendChild(label);

            let input;
            if (f.type === 'select') {
                input = h('select', { class: 'form-control' });
                for (const opt of f.options || []) {
                    const optEl = h('option', { value: opt.value, text: opt.label });
                    input.appendChild(optEl);
                }
            } else {
                input = h('input', {
                    type: f.type || 'text',
                    class: 'form-control',
                    placeholder: f.placeholder || ''
                });
            }

            if (f.value !== undefined) {
                input.value = f.value;
            }

            fieldInputs[f.name] = input;
            group.appendChild(input);
            form.appendChild(group);
        }

        const btn = h('button', { type: 'submit', class: 'btn btn-primary', text: this.submitText });
        form.appendChild(btn);

        form.onsubmit = async (e) => {
            e.preventDefault();
            btn.disabled = true;

            const payload = {};
            for (const [name, input] of Object.entries(fieldInputs)) {
                payload[name] = input.value;
            }

            const idemKey = crypto.randomUUID();
            const res = await api(this.method, this.endpoint, payload, { idempotencyKey: idemKey });
            btn.disabled = false;

            if (res && res.status >= 200 && res.status < 300) {
                toast('Saved successfully!', 'success');
                this.onSuccess(res.data);
            } else {
                const msg = res?.data?.error?.message || 'Failed to submit form.';
                toast(msg, 'danger');
            }
        };

        this.container.appendChild(form);
    }
}
