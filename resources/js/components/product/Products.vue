<template>
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Product list <span class="text-secondary small">({{ products.total || 0 }})</span></h3>
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
                <th scope="col">Photo</th>
                <th scope="col">Name</th>
                <th scope="col">Description</th>
                <th scope="col">Category</th>
                <th scope="col">Tags</th>
                <th scope="col" class="text-end">Price</th>
                <th scope="col" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in products.data || []" :key="product.id">
                <td>{{ product.id }}</td>
                <td>
                  <img v-if="photoUrl(product)" :src="photoUrl(product)" alt="" class="product-thumb" loading="lazy">
                  <span v-else class="product-thumb d-inline-flex align-items-center justify-content-center text-secondary">
                    <i class="fa-solid fa-image" aria-hidden="true"></i>
                  </span>
                </td>
                <td>{{ product.name }}</td>
                <td>{{ truncate(product.description, 60) }}</td>
                <td class="text-capitalize">{{ product.category?.name || '—' }}</td>
                <td>
                  <span v-for="tag in product.tags || []" :key="tag.id" class="badge text-bg-info me-1">{{ tag.text }}</span>
                  <span v-if="!(product.tags || []).length" class="text-secondary">—</span>
                </td>
                <td class="text-end">{{ formatCurrency(product.price) }}</td>
                <td class="text-end text-nowrap">
                  <button type="button" class="btn btn-sm btn-outline-primary me-1" :aria-label="`Edit ${product.name}`" @click="openEdit(product)">
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                  </button>
                  <button v-if="$gate.isAdmin()" type="button" class="btn btn-sm btn-outline-danger" :aria-label="`Delete ${product.name}`" @click="remove(product)">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="loaded && !(products.data || []).length">
                <td colspan="8" class="text-center text-secondary py-4">No products yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-footer clearfix">
          <pagination :data="products" @pagination-change-page="load" />
        </div>
      </div>
    </div>

    <div ref="modal" class="modal fade" tabindex="-1" aria-labelledby="productModalTitle" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <form class="modal-content" novalidate @submit.prevent="save">
          <div class="modal-header">
            <h5 id="productModalTitle" class="modal-title">{{ editMode ? 'Update product' : 'Create new product' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-8">
                <div class="mb-3">
                  <label for="product_name" class="form-label">Name</label>
                  <input id="product_name" v-model="form.name" type="text" maxlength="191" class="form-control" :class="{ 'is-invalid': form.errors.has('name') }">
                  <has-error :form="form" field="name" />
                </div>
                <div class="mb-3">
                  <label for="product_description" class="form-label">Description</label>
                  <textarea id="product_description" v-model="form.description" rows="3" maxlength="1000" class="form-control" :class="{ 'is-invalid': form.errors.has('description') }"></textarea>
                  <has-error :form="form" field="description" />
                </div>
                <div class="row">
                  <div class="col-sm-6 mb-3">
                    <label for="product_price" class="form-label">Price</label>
                    <input id="product_price" v-model="form.price" type="number" min="0" step="0.01" class="form-control" :class="{ 'is-invalid': form.errors.has('price') }">
                    <has-error :form="form" field="price" />
                  </div>
                  <div class="col-sm-6 mb-3">
                    <label for="product_category" class="form-label">Category</label>
                    <select id="product_category" v-model="form.category_id" class="form-select" :class="{ 'is-invalid': form.errors.has('category_id') }">
                      <option disabled value="">Select category</option>
                      <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                    <has-error :form="form" field="category_id" />
                  </div>
                </div>
                <div class="mb-3">
                  <label for="product_tags" class="form-label">Tags</label>
                  <tags-input
                    id="product_tags"
                    v-model="tagInput"
                    :tags="form.tags"
                    :autocomplete-items="tagSuggestions"
                    :invalid="hasTagErrors"
                    placeholder="Type a tag and press Enter"
                    @tags-changed="tags => (form.tags = tags)"
                  />
                  <div v-if="hasTagErrors" class="invalid-feedback d-block" role="alert">{{ tagError }}</div>
                </div>
              </div>

              <div class="col-md-4">
                <label for="product_photo" class="form-label">Photo</label>
                <div class="mb-2 text-center">
                  <img v-if="photoPreview" :src="photoPreview" alt="Product photo preview" class="photo-preview">
                  <div v-else class="photo-preview d-flex align-items-center justify-content-center text-secondary py-5">
                    <i class="fa-solid fa-image fa-2x" aria-hidden="true"></i>
                  </div>
                </div>
                <input
                  id="product_photo"
                  ref="file"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  class="form-control form-control-sm"
                  :class="{ 'is-invalid': form.errors.has('photo') }"
                  :disabled="uploading"
                  aria-describedby="product_photo_help"
                  @change="upload"
                >
                <div id="product_photo_help" class="form-text">JPG, PNG or WebP, max 5 MB, up to 4096×4096 px.</div>
                <div v-if="uploading" class="small text-secondary mt-1">
                  <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Uploading…
                </div>
                <has-error :form="form" field="photo" />
                <button v-if="photoPreview" type="button" class="btn btn-sm btn-outline-secondary mt-2" @click="clearPhoto">Remove photo</button>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" :disabled="form.busy || uploading">{{ editMode ? 'Update' : 'Create' }}</button>
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
import { formatCurrency, storageUrl, truncate } from '../../lib/format';
import { confirmDelete, errorMessage, toastError, toastSuccess } from '../../lib/notify';
import TagsInput from '../common/TagsInput.vue';

const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export default {
    components: { TagsInput },
    data() {
        return {
            editMode: false,
            loaded: false,
            page: 1,
            products: {},
            categories: [],
            tagOptions: [],
            tagInput: '',
            uploading: false,
            photoPreview: null,
            photoChanged: false,
            form: new Form({ id: '', name: '', description: '', price: '', category_id: '', tags: [], photo: null }),
        };
    },
    computed: {
        tagSuggestions() {
            const term = this.tagInput.trim().toLowerCase();
            return term ? this.tagOptions.filter(tag => tag.text.toLowerCase().includes(term)) : this.tagOptions;
        },
        hasTagErrors() {
            return Object.keys(this.form.errors.fields).some(key => key === 'tags' || key.startsWith('tags.'));
        },
        tagError() {
            const key = Object.keys(this.form.errors.fields).find(k => k === 'tags' || k.startsWith('tags.'));
            return key ? this.form.errors.get(key) : '';
        },
    },
    created() {
        this.load();
        this.loadCategories();
        this.loadTags();
    },
    beforeUnmount() {
        hideModal(this.$refs.modal);
    },
    methods: {
        formatCurrency,
        truncate,
        photoUrl(product) {
            return product.photo_url || storageUrl(product.photo);
        },
        async load(page = this.page) {
            try {
                const { data } = await track(axios.get('/api/product', { params: { page } }));
                this.products = data.data || {};
                this.page = this.products.current_page || page;
                if (!(this.products.data || []).length && page > 1) return this.load(page - 1);
            } catch (error) {
                toastError(errorMessage(error, 'Unable to load products.'));
            } finally {
                this.loaded = true;
            }
        },
        async loadCategories() {
            try {
                const { data } = await axios.get('/api/category/list');
                // `pluck('name', 'id')` => { "<id>": "<name>" }
                this.categories = Object.entries(data.data || {}).map(([id, name]) => ({ id: Number(id), name }));
            } catch (error) {
                toastError(errorMessage(error, 'Unable to load categories.'));
            }
        },
        async loadTags() {
            try {
                const { data } = await axios.get('/api/tag/list');
                this.tagOptions = (data.data || []).map(tag => ({ id: tag.id, text: tag.name }));
            } catch (error) {
                toastError(errorMessage(error, 'Unable to load tags.'));
            }
        },
        resetModal() {
            this.form.reset();
            this.tagInput = '';
            this.photoPreview = null;
            this.photoChanged = false;
            if (this.$refs.file) this.$refs.file.value = '';
        },
        openCreate() {
            this.editMode = false;
            this.resetModal();
            showModal(this.$refs.modal);
        },
        openEdit(product) {
            this.editMode = true;
            this.resetModal();
            this.form.fill({
                id: product.id,
                name: product.name,
                description: product.description,
                price: product.price,
                category_id: product.category_id ?? product.category?.id ?? '',
                tags: (product.tags || []).map(tag => ({ text: tag.text ?? tag.name })),
                photo: product.photo || null,
            });
            this.photoPreview = this.photoUrl(product);
            showModal(this.$refs.modal);
        },
        async upload(event) {
            const file = event.target.files?.[0];
            this.form.errors.clear('photo');
            if (!file) return;
            if (!ALLOWED_TYPES.includes(file.type)) {
                this.form.errors.fields = { ...this.form.errors.fields, photo: ['The photo must be a JPG, PNG or WebP image.'] };
                event.target.value = '';
                return;
            }
            if (file.size > MAX_UPLOAD_BYTES) {
                this.form.errors.fields = { ...this.form.errors.fields, photo: ['The photo may not be larger than 5 MB.'] };
                event.target.value = '';
                return;
            }

            const body = new FormData();
            body.append('file', file);
            this.uploading = true;
            try {
                const { data } = await track(axios.post('/api/product/upload', body));
                if (!data?.success || !data.data?.path) throw new Error(data?.message || 'Upload failed');
                this.form.photo = data.data.path;
                this.photoPreview = data.data.url || storageUrl(data.data.path);
                this.photoChanged = true;
            } catch (error) {
                const messages = error.response?.data?.errors?.file || [errorMessage(error, 'Upload failed.')];
                this.form.errors.fields = { ...this.form.errors.fields, photo: messages };
                event.target.value = '';
            } finally {
                this.uploading = false;
            }
        },
        clearPhoto() {
            this.form.photo = null;
            this.photoPreview = null;
            this.photoChanged = true;
            if (this.$refs.file) this.$refs.file.value = '';
        },
        payload() {
            const data = {
                name: this.form.name,
                description: this.form.description,
                price: this.form.price,
                category_id: this.form.category_id === '' ? null : Number(this.form.category_id),
                tags: this.form.tags.map(tag => ({ text: tag.text })),
            };
            // Only send `photo` for new products or when it actually changed, so
            // legacy photo values are not re-validated on unrelated edits.
            if (!this.editMode || this.photoChanged) data.photo = this.form.photo || null;
            return data;
        },
        async save() {
            try {
                const response = this.editMode
                    ? await track(this.form.put(`/api/product/${this.form.id}`, this.payload()))
                    : await track(this.form.post('/api/product', this.payload()));
                hideModal(this.$refs.modal);
                toastSuccess(response.data.message);
                this.load();
                this.loadTags();
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
        async remove(product) {
            if (!(await confirmDelete(`product "${product.name}"`))) return;
            try {
                const response = await track(axios.delete(`/api/product/${product.id}`));
                toastSuccess(response.data.message);
                this.load();
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
    },
};
</script>
