<template>
  <div class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">Personal access tokens</h3>
      <div v-if="!unavailable" class="card-tools">
        <button type="button" class="btn btn-sm btn-primary" @click="openCreate">
          <i class="fa-solid fa-key me-1" aria-hidden="true"></i> Create new token
        </button>
      </div>
    </div>
    <div class="card-body" :class="{ 'p-0': tokens.length && !loadError }">
      <div v-if="loadError" class="alert mb-0" :class="unavailable ? 'alert-secondary' : 'alert-danger'" role="alert">
        <i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><template v-if="unavailable">Passport's JSON API is not available on this server. Use <code>php artisan passport:client</code> to manage OAuth clients from the command line.</template><template v-else>{{ loadError }}</template>
      </div>
      <p v-else-if="loaded && !tokens.length" class="mb-0 text-secondary">You have not created any personal access tokens.</p>
      <div v-else-if="tokens.length" class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Scopes</th>
              <th scope="col">Created</th>
              <th scope="col">Expires</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="token in tokens" :key="token.id">
              <td>{{ token.name || '—' }}</td>
              <td>{{ (token.scopes || []).join(', ') || '—' }}</td>
              <td>{{ formatDate(token.created_at) }}</td>
              <td>{{ formatDate(token.expires_at) }}</td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" @click="revoke(token)">Revoke</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div ref="modal" class="modal fade" tabindex="-1" aria-labelledby="patModalTitle" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" novalidate @submit.prevent="store">
          <div class="modal-header">
            <h5 id="patModalTitle" class="modal-title">Create token</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div v-if="formError" class="alert alert-danger" role="alert">{{ formError }}</div>
            <div class="mb-3">
              <label for="pat_name" class="form-label">Name</label>
              <input id="pat_name" v-model="form.name" type="text" maxlength="255" class="form-control" :class="{ 'is-invalid': errors.name }">
              <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
            </div>
            <fieldset v-if="scopes.length">
              <legend class="form-label fs-6">Scopes</legend>
              <div v-for="scope in scopes" :key="scope.id" class="form-check">
                <input :id="`pat_scope_${scope.id}`" v-model="form.scopes" class="form-check-input" type="checkbox" :value="scope.id">
                <label :for="`pat_scope_${scope.id}`" class="form-check-label">{{ scope.id }}<span v-if="scope.description" class="text-secondary"> — {{ scope.description }}</span></label>
              </div>
              <div v-if="errors.scopes" class="invalid-feedback d-block">{{ errors.scopes[0] }}</div>
            </fieldset>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" :disabled="busy">Create</button>
          </div>
        </form>
      </div>
    </div>

    <div ref="tokenModal" class="modal fade" tabindex="-1" aria-labelledby="patTokenTitle" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="patTokenTitle" class="modal-title">Personal access token</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning" role="alert">
              Copy this token now. It will not be shown again. Treat it like a password.
            </div>
            <textarea class="form-control font-monospace secret-value" rows="8" readonly aria-label="Personal access token" :value="accessToken"></textarea>
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

export default {
    data() {
        return {
            tokens: [],
            scopes: [],
            loaded: false,
            loadError: '',
            unavailable: false,
            busy: false,
            form: { name: '', scopes: [] },
            errors: {},
            formError: '',
            accessToken: '',
        };
    },
    created() {
        this.load();
        this.loadScopes();
    },
    mounted() {
        // Drop the plaintext token from memory once the dialog is closed.
        this.$refs.tokenModal.addEventListener('hidden.bs.modal', this.forgetToken);
    },
    beforeUnmount() {
        this.$refs.tokenModal?.removeEventListener('hidden.bs.modal', this.forgetToken);
        hideModal(this.$refs.modal);
        hideModal(this.$refs.tokenModal);
        this.forgetToken();
    },
    methods: {
        async load() {
            try {
                const { data } = await axios.get('/oauth/personal-access-tokens');
                this.tokens = Array.isArray(data) ? data : [];
                this.loadError = '';
                this.unavailable = false;
            } catch (error) {
                const { unavailable, message } = describePassportError(error, 'Unable to load personal access tokens.');
                this.unavailable = unavailable;
                this.loadError = message;
            } finally {
                this.loaded = true;
            }
        },
        async loadScopes() {
            try {
                const { data } = await axios.get('/oauth/scopes');
                this.scopes = Array.isArray(data) ? data : [];
            } catch {
                this.scopes = [];
            }
        },
        openCreate() {
            this.form = { name: '', scopes: [] };
            this.errors = {};
            this.formError = '';
            showModal(this.$refs.modal);
        },
        async store() {
            this.busy = true;
            this.errors = {};
            this.formError = '';
            try {
                const { data } = await axios.post('/oauth/personal-access-tokens', this.form);
                hideModal(this.$refs.modal);
                this.accessToken = data?.accessToken || data?.access_token || '';
                if (this.accessToken) showModal(this.$refs.tokenModal);
                this.load();
            } catch (error) {
                this.errors = validationErrors(error);
                if (!Object.keys(this.errors).length) this.formError = describePassportError(error, 'Unable to create the token.').message;
            } finally {
                this.busy = false;
            }
        },
        async revoke(token) {
            if (!(await confirmDelete(`token "${token.name || token.id}"`))) return;
            try {
                await axios.delete(`/oauth/personal-access-tokens/${encodeURIComponent(token.id)}`);
                toastSuccess('Token revoked');
                this.load();
            } catch (error) {
                toastError(describePassportError(error, 'Unable to revoke the token.').message);
            }
        },
        forgetToken() {
            this.accessToken = '';
        },
    },
};
</script>
