import DashboardPage from './components/DashboardPage.vue';
import ProfilePage from './components/ProfilePage.vue';
import DeveloperPage from './components/DeveloperPage.vue';
import UsersPage from './components/UsersPage.vue';
import ProductsPage from './components/product/ProductsPage.vue';
import TagsPage from './components/product/TagsPage.vue';
import CategoriesPage from './components/product/CategoriesPage.vue';
import NotFound from './components/NotFound.vue';

// meta.admin: UI-only guard (App.vue renders NotFound for non-admins).
// The API enforces the same rules server-side.
export default [
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', component: DashboardPage, meta: { title: 'Dashboard' } },
    { path: '/profile', component: ProfilePage, meta: { title: 'Profile' } },
    { path: '/developer', component: DeveloperPage, meta: { title: 'Developer' } },
    { path: '/users', component: UsersPage, meta: { title: 'Users', admin: true } },
    { path: '/products', alias: '/product', component: ProductsPage, meta: { title: 'Products', section: 'product' } },
    { path: '/product/tag', component: TagsPage, meta: { title: 'Tags', section: 'product', admin: true } },
    {
        path: '/product/category',
        component: CategoriesPage,
        meta: { title: 'Categories', section: 'product', admin: true },
    },
    { path: '/:pathMatch(.*)*', component: NotFound, meta: { title: 'Page not found' } },
];
