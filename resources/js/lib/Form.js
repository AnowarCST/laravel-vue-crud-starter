import axios from 'axios';
import { toRaw } from 'vue';

/**
 * Minimal replacement for the abandoned `vform` package.
 * Keeps only the declared fields and exposes Laravel 422 validation errors.
 */
export class Form {
    constructor(fields) {
        this.defaults = structuredClone(fields);
        Object.assign(this, structuredClone(fields));
        this.busy = false;
        this.errors = {
            fields: {},
            has(field) {
                return Boolean(this.fields[field]);
            },
            get(field) {
                return this.fields[field]?.[0] || '';
            },
            clear(field) {
                if (field) delete this.fields[field];
                else this.fields = {};
            },
        };
    }

    data() {
        return Object.fromEntries(Object.keys(this.defaults).map((key) => [key, this[key]]));
    }

    fill(values = {}) {
        for (const key of Object.keys(this.defaults)) {
            if (key in values) this[key] = values[key];
        }
    }

    reset() {
        // Instances usually live in reactive component state; unwrap the proxy
        // because structuredClone cannot clone Proxy objects.
        Object.assign(this, structuredClone(toRaw(this.defaults)));
        this.errors.clear();
    }

    async submit(method, url, data = this.data()) {
        this.errors.clear();
        this.busy = true;
        try {
            return await axios({ method, url, data });
        } catch (error) {
            if (error.response?.status === 422) {
                this.errors.fields = error.response.data?.errors || {};
            }
            throw error;
        } finally {
            this.busy = false;
        }
    }

    post(url, data) {
        return this.submit('post', url, data);
    }
    put(url, data) {
        return this.submit('put', url, data);
    }
}
