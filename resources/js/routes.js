import Dashboard from './components/Dashboard.vue';
import Profile from './components/Profile.vue';
import Developer from './components/Developer.vue';
import Users from './components/Users.vue';
import Products from './components/product/Products.vue';
import Tag from './components/product/Tag.vue';
import Category from './components/product/Category.vue';
import NotFound from './components/NotFound.vue';

// meta.admin: UI-only guard (App.vue renders NotFound for non-admins).
// The API enforces the same rules server-side.
export default [
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', component: Dashboard, meta: { title: 'Dashboard' } },
    { path: '/profile', component: Profile, meta: { title: 'Profile' } },
    { path: '/developer', component: Developer, meta: { title: 'Developer' } },
    { path: '/users', component: Users, meta: { title: 'Users', admin: true } },
    { path: '/products', alias: '/product', component: Products, meta: { title: 'Products', section: 'product' } },
    { path: '/product/tag', component: Tag, meta: { title: 'Tags', section: 'product', admin: true } },
    { path: '/product/category', component: Category, meta: { title: 'Categories', section: 'product', admin: true } },
    { path: '/:pathMatch(.*)*', component: NotFound, meta: { title: 'Page not found' } },
];
