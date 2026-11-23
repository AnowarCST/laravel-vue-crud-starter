<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Category list</h3>
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
                                <th scope="col">Description</th>
                                <th scope="col">Created</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="category in categories.data || []" :key="category.id">
                                <td>{{ category.id }}</td>
                                <td class="text-capitalize">{{ category.name }}</td>
                                <td>{{ category.description || '—' }}</td>
                                <td>{{ formatDate(category.created_at) }}</td>
                                <td class="text-end text-nowrap">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary me-1"
                                        :aria-label="`Edit ${category.name}`"
                                        @click="openEdit(category)"
                                    >
                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        :aria-label="`Delete ${category.name}`"
                                        @click="remove(category)"
                                    >
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="loaded && !(categories.data || []).length">
                                <td colspan="5" class="text-center text-secondary py-4">No categories yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    <pagination-nav :data="categories" @pagination-change-page="load" />
                </div>
            </div>
        </div>

        <div ref="modal" class="modal fade" tabindex="-1" aria-labelledby="categoryModalTitle" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" novalidate @submit.prevent="save">
                    <div class="modal-header">
                        <h5 id="categoryModalTitle" class="modal-title">
                            {{ editMode ? 'Update category' : 'Create new category' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Name</label>
                            <input
                                id="category_name"
                                v-model="form.name"
                                type="text"
                                maxlength="191"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.has('name') }"
                            />
                            <has-error :form="form" field="name" />
                        </div>
                        <div class="mb-3">
                            <label for="category_description" class="form-label">Description</label>
                            <textarea
                                id="category_description"
                                v-model="form.description"
                                rows="3"
                                maxlength="1000"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.has('description') }"
                            ></textarea>
                            <has-error :form="form" field="description" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" :disabled="form.busy">
                            {{ editMode ? 'Update' : 'Create' }}
                        </button>
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
            categories: {},
            form: new Form({ id: '', name: '', description: '' }),
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
                const { data } = await track(axios.get('/api/category', { params: { page } }));
                this.categories = data.data || {};
                this.page = this.categories.current_page || page;
                if (!(this.categories.data || []).length && page > 1) return this.load(page - 1);
            } catch (error) {
                toastError(errorMessage(error, 'Unable to load categories.'));
            } finally {
                this.loaded = true;
            }
        },
        openCreate() {
            this.editMode = false;
            this.form.reset();
            showModal(this.$refs.modal);
        },
        openEdit(category) {
            this.editMode = true;
            this.form.reset();
            this.form.fill({ ...category, description: category.description ?? '' });
            showModal(this.$refs.modal);
        },
        async save() {
            const payload = { name: this.form.name, description: this.form.description || null };
            try {
                const response = this.editMode
                    ? await track(this.form.put(`/api/category/${this.form.id}`, payload))
                    : await track(this.form.post('/api/category', payload));
                hideModal(this.$refs.modal);
                toastSuccess(response.data.message);
                this.load();
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
        async remove(category) {
            if (!(await confirmDelete(`category "${category.name}"`))) return;
            try {
                const response = await track(axios.delete(`/api/category/${category.id}`));
                toastSuccess(response.data.message);
                this.load();
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
    },
};
</script>
