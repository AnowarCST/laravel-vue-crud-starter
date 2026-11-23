<template>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">OAuth clients</h3>
            <div v-if="!unavailable" class="card-tools">
                <button type="button" class="btn btn-sm btn-primary" @click="openCreate">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i> Create new client
                </button>
            </div>
        </div>
        <div class="card-body" :class="{ 'p-0': clients.length && !loadError }">
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
            <p v-else-if="loaded && !clients.length" class="mb-0 text-secondary">
                You have not created any OAuth clients.
            </p>
            <div v-else-if="clients.length" class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Client ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Redirect URIs</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="client in clients" :key="client.id">
                            <td>
                                <code class="secret-value">{{ client.id }}</code>
                            </td>
                            <td>{{ client.name }}</td>
                            <td class="text-break">{{ redirectsOf(client).join(', ') || '—' }}</td>
                            <td class="text-end text-nowrap">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary me-1"
                                    :aria-label="`Edit ${client.name}`"
                                    @click="openEdit(client)"
                                >
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    :aria-label="`Delete ${client.name}`"
                                    @click="destroy(client)"
                                >
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div ref="modal" class="modal fade" tabindex="-1" aria-labelledby="clientModalTitle" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" novalidate @submit.prevent="save">
                    <div class="modal-header">
                        <h5 id="clientModalTitle" class="modal-title">
                            {{ form.id ? 'Edit client' : 'Create client' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger" role="alert">{{ formError }}</div>
                        <div class="mb-3">
                            <label for="client_name" class="form-label">Name</label>
                            <input
                                id="client_name"
                                v-model="form.name"
                                type="text"
                                maxlength="255"
                                class="form-control"
                                :class="{ 'is-invalid': errors.name }"
                            />
                            <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                            <div class="form-text">Something your users will recognize and trust.</div>
                        </div>
                        <div class="mb-3">
                            <label for="client_redirect" class="form-label">Redirect URL(s)</label>
                            <input
                                id="client_redirect"
                                v-model="form.redirect"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.redirect }"
                            />
                            <div v-if="errors.redirect" class="invalid-feedback">{{ errors.redirect[0] }}</div>
                            <div class="form-text">
                                Your application's authorization callback URL. Separate multiple URLs with commas.
                            </div>
                        </div>
                        <div v-if="!form.id" class="form-check">
                            <input
                                id="client_confidential"
                                v-model="form.confidential"
                                class="form-check-input"
                                type="checkbox"
                            />
                            <label for="client_confidential" class="form-check-label"
                                >Confidential client (has a secret)</label
                            >
                            <div class="form-text">
                                Uncheck for SPAs / mobile apps that cannot keep a secret; they must use PKCE.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" :disabled="busy">
                            {{ form.id ? 'Save changes' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div ref="secretModal" class="modal fade" tabindex="-1" aria-labelledby="clientSecretTitle" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="clientSecretTitle" class="modal-title">Client created</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">Client ID</p>
                        <p>
                            <code class="secret-value">{{ created.id }}</code>
                        </p>
                        <template v-if="created.secret">
                            <p class="mb-1">Client secret</p>
                            <p>
                                <code class="secret-value">{{ created.secret }}</code>
                            </p>
                            <div class="alert alert-warning mb-0" role="alert">
                                Copy the secret now. It is stored hashed and will not be shown again.
                            </div>
                        </template>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { showModal, hideModal } from '../../bootstrap';
import { confirmDelete, toastError, toastSuccess } from '../../lib/notify';
import { describePassportError, validationErrors } from '../../lib/passport';

const blankForm = () => ({ id: null, name: '', redirect: '', confidential: true });

export default {
    data() {
        return {
            clients: [],
            loaded: false,
            loadError: '',
            unavailable: false,
            busy: false,
            form: blankForm(),
            errors: {},
            formError: '',
            created: { id: '', secret: '' },
        };
    },
    created() {
        this.load();
    },
    mounted() {
        // Never keep a plaintext secret around after the dialog closes.
        this.$refs.secretModal.addEventListener('hidden.bs.modal', this.forgetSecret);
    },
    beforeUnmount() {
        this.$refs.secretModal?.removeEventListener('hidden.bs.modal', this.forgetSecret);
        hideModal(this.$refs.modal);
        hideModal(this.$refs.secretModal);
        this.forgetSecret();
    },
    methods: {
        redirectsOf(client) {
            if (Array.isArray(client.redirect_uris)) return client.redirect_uris;
            return client.redirect ? String(client.redirect).split(',') : [];
        },
        async load() {
            try {
                const { data } = await axios.get('/oauth/clients');
                this.clients = Array.isArray(data) ? data : [];
                this.loadError = '';
                this.unavailable = false;
            } catch (error) {
                const { unavailable, message } = describePassportError(error, 'Unable to load OAuth clients.');
                this.unavailable = unavailable;
                this.loadError = message;
            } finally {
                this.loaded = true;
            }
        },
        openCreate() {
            this.form = blankForm();
            this.errors = {};
            this.formError = '';
            showModal(this.$refs.modal);
        },
        openEdit(client) {
            this.form = {
                id: client.id,
                name: client.name,
                redirect: this.redirectsOf(client).join(','),
                confidential: true,
            };
            this.errors = {};
            this.formError = '';
            showModal(this.$refs.modal);
        },
        async save() {
            this.busy = true;
            this.errors = {};
            this.formError = '';
            const payload = { name: this.form.name, redirect: this.form.redirect };
            try {
                if (this.form.id) {
                    await axios.put(`/oauth/clients/${encodeURIComponent(this.form.id)}`, payload);
                    hideModal(this.$refs.modal);
                    toastSuccess('Client updated');
                } else {
                    const { data } = await axios.post('/oauth/clients', {
                        ...payload,
                        confidential: Boolean(this.form.confidential),
                    });
                    hideModal(this.$refs.modal);
                    this.created = {
                        id: data?.id ?? '',
                        secret: data?.plain_secret || data?.plainSecret || data?.secret || '',
                    };
                    showModal(this.$refs.secretModal);
                }
                this.load();
            } catch (error) {
                this.errors = validationErrors(error);
                if (!Object.keys(this.errors).length)
                    this.formError = describePassportError(error, 'Unable to save the client.').message;
            } finally {
                this.busy = false;
            }
        },
        async destroy(client) {
            if (!(await confirmDelete(`client "${client.name}"`))) return;
            try {
                await axios.delete(`/oauth/clients/${encodeURIComponent(client.id)}`);
                toastSuccess('Client deleted');
                this.load();
            } catch (error) {
                toastError(describePassportError(error, 'Unable to delete the client.').message);
            }
        },
        forgetSecret() {
            this.created = { id: '', secret: '' };
        },
    },
};
</script>
