<template>
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Tag list</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-sm btn-primary" @click="openCreate">
              <i class="fa-solid fa-plus me-1" aria-hidden="true"></i> Add new
            </button>
          </div>
        </div>
        <div class="card-body table-responsive p-0">
          <table class="table table-hover table-striped align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Name</th>
                <th scope="col">Created</th>
                <th scope="col" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tag in tags.data || []" :key="tag.id">
                <td>{{ tag.id }}</td>
                <td>{{ tag.name }}</td>
                <td>{{ formatDate(tag.created_at) }}</td>
                <td class="text-end text-nowrap">
                  <button type="button" class="btn btn-sm btn-outline-primary me-1" :aria-label="`Edit ${tag.name}`" @click="openEdit(tag)">
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger" :aria-label="`Delete ${tag.name}`" @click="remove(tag)">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="loaded && !(tags.data || []).length">
                <td colspan="4" class="text-center text-secondary py-4">No tags yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-footer clearfix">
          <pagination :data="tags" @pagination-change-page="load" />
        </div>
      </div>
    </div>

    <div ref="modal" class="modal fade" tabindex="-1" aria-labelledby="tagModalTitle" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" novalidate @submit.prevent="save">
          <div class="modal-header">
            <h5 id="tagModalTitle" class="modal-title">{{ editMode ? 'Update tag' : 'Create new tag' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label for="tag_name" class="form-label">Name</label>
            <input id="tag_name" v-model="form.name" type="text" maxlength="191" class="form-control" :class="{ 'is-invalid': form.errors.has('name') }">
            <has-error :form="form" field="name" />
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
import { Form } from '../../lib/Form';
import { track } from '../../lib/progress';
import { showModal, hideModal } from '../../bootstrap';
import { confirmDelete, errorMessage, toastError, toastSuccess } from '../../lib/notify';

export default {
    data() {
        return {
            editMode: false,
            loaded: false,
            page: 1,
            tags: {},
            form: new Form({ id: '', name: '' }),
        };
    },
    created() {
        this.load();
    },
    beforeUnmount() {
        hideModal(this.$refs.modal);
    },
    methods: {
        async load(page = this.page) {
            try {
                const { data } = await track(axios.get('/api/tag', { params: { page } }));
                this.tags = data.data || {};
                this.page = this.tags.current_page || page;
                if (!(this.tags.data || []).length && page > 1) return this.load(page - 1);
            } catch (error) {
                toastError(errorMessage(error, 'Unable to load tags.'));
            } finally {
                this.loaded = true;
            }
        },
        openCreate() {
            this.editMode = false;
            this.form.reset();
            showModal(this.$refs.modal);
        },
        openEdit(tag) {
            this.editMode = true;
            this.form.reset();
            this.form.fill(tag);
            showModal(this.$refs.modal);
        },
        async save() {
            const payload = { name: this.form.name };
            try {
                const response = this.editMode
                    ? await track(this.form.put(`/api/tag/${this.form.id}`, payload))
                    : await track(this.form.post('/api/tag', payload));
                hideModal(this.$refs.modal);
                toastSuccess(response.data.message);
                this.load();
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
        async remove(tag) {
            if (!(await confirmDelete(`tag "${tag.name}"`))) return;
            try {
                const response = await track(axios.delete(`/api/tag/${tag.id}`));
                toastSuccess(response.data.message);
                this.load();
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
    },
};
</script>
