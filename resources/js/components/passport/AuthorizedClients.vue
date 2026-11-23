<template>
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Authorized applications</h3></div>
        <div class="card-body" :class="{ 'p-0': tokens.length && !loadError }">
            <div
                v-if="loadError"
                class="alert mb-0"
                :class="unavailable ? 'alert-secondary' : 'alert-danger'"
                role="alert"
            >
                <i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i
                ><template v-if="unavailable"
                    >Passport's JSON API is not available on this server. Use
                    <code>php artisan passport:client</code> to manage OAuth clients from the command line.</template
                ><template v-else>{{ loadError }}</template>
            </div>
            <p v-else-if="loaded && !tokens.length" class="mb-0 text-secondary">
                You have not authorized any third-party applications.
            </p>
            <div v-else-if="tokens.length" class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Application</th>
                            <th scope="col">Scopes</th>
                            <th scope="col">Expires</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="token in tokens" :key="token.id">
                            <td>{{ token.client?.name || 'Unknown client' }}</td>
                            <td>{{ (token.scopes || []).join(', ') || '—' }}</td>
                            <td>{{ formatDate(token.expires_at) }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="revoke(token)">
                                    Revoke
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { confirmDelete, toastError, toastSuccess } from '../../lib/notify';
import { describePassportError } from '../../lib/passport';

export default {
    data() {
        return { tokens: [], loaded: false, loadError: '', unavailable: false };
    },
    created() {
        this.load();
    },
    methods: {
        async load() {
            try {
                const { data } = await axios.get('/oauth/tokens');
                this.tokens = Array.isArray(data) ? data : [];
                this.loadError = '';
                this.unavailable = false;
            } catch (error) {
                const { unavailable, message } = describePassportError(
                    error,
                    'Unable to load authorized applications.',
                );
                this.unavailable = unavailable;
                this.loadError = message;
            } finally {
                this.loaded = true;
            }
        },
        async revoke(token) {
            if (!(await confirmDelete(`access for "${token.client?.name || 'this application'}"`))) return;
            try {
                await axios.delete(`/oauth/tokens/${encodeURIComponent(token.id)}`);
                toastSuccess('Access revoked');
                this.load();
            } catch (error) {
                toastError(describePassportError(error, 'Unable to revoke access.').message);
            }
        },
    },
};
</script>
