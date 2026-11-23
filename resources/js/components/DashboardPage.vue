<template>
    <div>
        <div class="row">
            <div v-for="box in boxes" :key="box.label" class="col-lg-3 col-6">
                <div class="small-box" :class="box.theme">
                    <div class="inner">
                        <h3>{{ box.value ?? '—' }}</h3>
                        <p>{{ box.label }}</p>
                    </div>
                    <i class="small-box-icon fa-solid" :class="box.icon" aria-hidden="true"></i>
                    <router-link
                        :to="box.to"
                        class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                    >
                        More info <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </router-link>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Latest products</h3>
                        <div class="card-tools">
                            <router-link to="/products" class="btn btn-sm btn-outline-primary">View all</router-link>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Category</th>
                                    <th scope="col" class="text-end">Price</th>
                                    <th scope="col">Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in latestProducts" :key="product.id">
                                    <td>{{ product.name }}</td>
                                    <td class="text-capitalize">{{ product.category?.name || '—' }}</td>
                                    <td class="text-end">{{ formatCurrency(product.price) }}</td>
                                    <td>{{ formatDate(product.created_at) }}</td>
                                </tr>
                                <tr v-if="loaded && !latestProducts.length">
                                    <td colspan="4" class="text-center text-secondary py-4">No products yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">Welcome</h3></div>
                    <div class="card-body">
                        <p class="mb-2">
                            Signed in as <strong>{{ user.name }}</strong>
                            <span class="badge text-bg-secondary text-capitalize">{{ user.type }}</span>
                        </p>
                        <p class="text-secondary mb-0">Application version: {{ version || 'unknown' }}</p>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header"><h3 class="card-title">Shortcuts</h3></div>
                    <div class="list-group list-group-flush">
                        <router-link to="/products" class="list-group-item list-group-item-action"
                            ><i class="fa-solid fa-box me-2" aria-hidden="true"></i>Manage products</router-link
                        >
                        <router-link v-if="$gate.isAdmin()" to="/users" class="list-group-item list-group-item-action"
                            ><i class="fa-solid fa-users me-2" aria-hidden="true"></i>Manage users</router-link
                        >
                        <router-link to="/profile" class="list-group-item list-group-item-action"
                            ><i class="fa-solid fa-user-gear me-2" aria-hidden="true"></i>Edit profile</router-link
                        >
                        <router-link to="/developer" class="list-group-item list-group-item-action"
                            ><i class="fa-solid fa-code me-2" aria-hidden="true"></i>API tokens &amp; OAuth
                            clients</router-link
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { track } from '../lib/progress';
import { currentUser } from '../lib/user';
import { formatCurrency } from '../lib/format';

// Every number shown here comes from the API; nothing is mocked.
async function total(url) {
    try {
        const { data } = await axios.get(url);
        return typeof data?.data?.total === 'number' ? data.data.total : null;
    } catch {
        return null;
    }
}

export default {
    data() {
        return {
            user: currentUser,
            loaded: false,
            version: '',
            latestProducts: [],
            counts: { products: null, categories: null, tags: null, users: null },
        };
    },
    computed: {
        boxes() {
            const boxes = [
                {
                    label: 'Products',
                    value: this.counts.products,
                    icon: 'fa-box',
                    theme: 'text-bg-primary',
                    to: '/products',
                },
            ];
            if (this.$gate.isAdmin()) {
                boxes.push(
                    {
                        label: 'Categories',
                        value: this.counts.categories,
                        icon: 'fa-layer-group',
                        theme: 'text-bg-success',
                        to: '/product/category',
                    },
                    {
                        label: 'Tags',
                        value: this.counts.tags,
                        icon: 'fa-tags',
                        theme: 'text-bg-warning',
                        to: '/product/tag',
                    },
                    {
                        label: 'Users',
                        value: this.counts.users,
                        icon: 'fa-users',
                        theme: 'text-bg-danger',
                        to: '/users',
                    },
                );
            }
            return boxes;
        },
    },
    created() {
        this.load();
    },
    methods: {
        formatCurrency,
        async load() {
            const isAdmin = this.$gate.isAdmin();
            const [products, version, categories, tags, users] = await track(
                Promise.allSettled([
                    axios.get('/api/product'),
                    axios.get('/api/version'),
                    isAdmin ? total('/api/category') : null,
                    isAdmin ? total('/api/tag') : null,
                    isAdmin ? total('/api/user') : null,
                ]),
            );

            if (products.status === 'fulfilled') {
                const page = products.value.data?.data || {};
                this.counts.products = typeof page.total === 'number' ? page.total : null;
                this.latestProducts = (page.data || []).slice(0, 5);
            }
            if (version.status === 'fulfilled' && typeof version.value.data?.version === 'string') {
                this.version = version.value.data.version;
            }
            this.counts.categories = categories.value ?? null;
            this.counts.tags = tags.value ?? null;
            this.counts.users = users.value ?? null;
            this.loaded = true;
        },
    },
};
</script>
