<template>
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">User list <span class="text-secondary small">({{ users.total || 0 }})</span></h3>
          <div class="card-tools">
            <button type="button" class="btn btn-sm btn-primary" @click="openCreate">
              <i class="fa-solid fa-user-plus me-1" aria-hidden="true"></i> Add new
            </button>
          </div>
        </div>
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-striped align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Name</th>
                <th scope="col">Email</th>
                <th scope="col">Type</th>
                <th scope="col">Email verified</th>
                <th scope="col">Created</th>
                <th scope="col" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users.data || []" :key="user.id">
                <td>{{ user.id }}</td>
                <td>{{ user.name }}</td>
                <td>{{ user.email }}</td>
                <td><span class="badge text-capitalize" :class="user.type === 'admin' ? 'text-bg-primary' : 'text-bg-secondary'">{{ user.type }}</span></td>
                <td>
                  <span v-if="user.email_verified_at" class="text-success"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Yes</span>
                  <span v-else class="text-secondary">No</span>
                </td>
                <td>{{ formatDate(user.created_at) }}</td>
                <td class="text-end text-nowrap">
                  <button type="button" class="btn btn-sm btn-outline-primary me-1" :aria-label="`Edit ${user.name}`" @click="openEdit(user)">
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger" :aria-label="`Delete ${user.name}`" :disabled="user.id === me.id" @click="remove(user)">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="loaded && !(users.data || []).length">
                <td colspan="7" class="text-center text-secondary py-4">No users found.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-footer clearfix">
          <pagination :data="users" @pagination-change-page="load" />
        </div>
      </div>
    </div>

    <div ref="modal" class="modal fade" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" novalidate @submit.prevent="save">
          <div class="modal-header">
            <h5 id="userModalTitle" class="modal-title">{{ editMode ? 'Update user' : 'Create new user' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="user_name" class="form-label">Name</label>
              <input id="user_name" v-model="form.name" type="text" maxlength="191" autocomplete="off" class="form-control" :class="{ 'is-invalid': form.errors.has('name') }">
              <has-error :form="form" field="name" />
            </div>
            <div class="mb-3">
              <label for="user_email" class="form-label">Email</label>
              <input id="user_email" v-model="form.email" type="email" maxlength="191" autocomplete="off" class="form-control" :class="{ 'is-invalid': form.errors.has('email') }">
              <has-error :form="form" field="email" />
            </div>
            <div class="mb-3">
              <label for="user_password" class="form-label">Password</label>
              <input
                id="user_password"
                v-model="form.password"
                type="password"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
                class="form-control"
                :class="{ 'is-invalid': form.errors.has('password') }"
                :placeholder="editMode ? 'Leave blank to keep the current password' : ''"
                aria-describedby="user_password_help"
              >
              <div id="user_password_help" class="form-text">At least 12 characters.</div>
              <has-error :form="form" field="password" />
            </div>
            <div class="mb-3">
              <label for="user_type" class="form-label">Type</label>
              <select id="user_type" v-model="form.type" class="form-select" :class="{ 'is-invalid': form.errors.has('type') }">
                <option disabled value="">Select user role</option>
                <option value="admin">Admin</option>
                <option value="user">Standard user</option>
              </select>
              <has-error :form="form" field="type" />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" :disabled="form.busy">{{ editMode ? 'Update' : 'Create' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { Form } from '../lib/Form';
import { track } from '../lib/progress';
import { currentUser } from '../lib/user';
import { showModal, hideModal } from '../bootstrap';
import { confirmDelete, errorMessage, toastError, toastSuccess } from '../lib/notify';

export default {
    data() {
        return {
            me: currentUser,
            editMode: false,
            loaded: false,
            page: 1,
            users: {},
            form: new Form({ id: '', name: '', email: '', password: '', type: '' }),
        };
    },
    created() {
        if (this.$gate.isAdmin()) this.load();
    },
    beforeUnmount() {
        hideModal(this.$refs.modal);
    },
    methods: {
        async load(page = this.page) {
            try {
                const { data } = await track(axios.get('/api/user', { params: { page } }));
                this.users = data.data || {};
                this.page = this.users.current_page || page;
                if (!(this.users.data || []).length && page > 1) return this.load(page - 1);
            } catch (error) {
                toastError(errorMessage(error, 'Unable to load users.'));
            } finally {
                this.loaded = true;
            }
        },
        openCreate() {
            this.editMode = false;
            this.form.reset();
            showModal(this.$refs.modal);
        },
        openEdit(user) {
            this.editMode = true;
            this.form.reset();
            this.form.fill({ id: user.id, name: user.name, email: user.email, type: user.type });
            showModal(this.$refs.modal);
        },
        async save() {
            const payload = { name: this.form.name, email: this.form.email, type: this.form.type };
            // On update an empty password means "keep the current one".
            if (!this.editMode || this.form.password) payload.password = this.form.password;
            try {
                const response = this.editMode
                    ? await track(this.form.put(`/api/user/${this.form.id}`, payload))
                    : await track(this.form.post('/api/user', payload));
                this.form.password = '';
                hideModal(this.$refs.modal);
                toastSuccess(response.data.message);
                this.load();
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
        async remove(user) {
            if (!(await confirmDelete(`user "${user.name}"`))) return;
            try {
                const response = await track(axios.delete(`/api/user/${user.id}`));
                toastSuccess(response.data.message);
                this.load();
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
    },
};
</script>
