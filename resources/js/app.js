// Styles (all bundled locally by Vite; nothing is loaded from a CDN).
import '@fontsource/source-sans-3/300.css';
import '@fontsource/source-sans-3/400.css';
import '@fontsource/source-sans-3/400-italic.css';
import '@fontsource/source-sans-3/600.css';
import '@fontsource/source-sans-3/700.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import 'admin-lte/dist/css/adminlte.min.css'; // includes Bootstrap 5 CSS
import 'sweetalert2/dist/sweetalert2.min.css';
import '../css/app.css';

// Behaviour: Bootstrap 5 data-API (dropdowns, modals, collapse) and AdminLTE 4
// (sidebar push menu, treeview). Neither depends on jQuery.
import 'bootstrap';
import { initialize as initializeAdminLte } from 'admin-lte';
import './bootstrap';

import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import Gate from './Gate';
import routes from './routes';
import { currentUser } from './lib/user';
import { Progress } from './lib/progress';
import { formatDate } from './lib/format';
import HasError from './components/common/HasError.vue';
import Pagination from './components/common/Pagination.vue';
import NotFound from './components/NotFound.vue';

// Only precompiled SFC templates are mounted (runtime-only Vue build). The
// mount point is an empty element; server-rendered markup is never compiled.
const root = document.getElementById('spa-app');

if (root) {
    const router = createRouter({
        history: createWebHistory(),
        routes,
        scrollBehavior: () => ({ top: 0 }),
    });

    const app = createApp(App);
    app.config.globalProperties.$gate = new Gate(currentUser);
    app.config.globalProperties.$Progress = Progress;
    app.config.globalProperties.formatDate = formatDate;
    app.component('has-error', HasError);
    app.component('pagination', Pagination);
    app.component('not-found', NotFound);
    app.use(router);

    router.isReady().then(() => {
        app.mount(root);
        // AdminLTE wires its widgets on DOMContentLoaded, which has already
        // fired for this deferred module. Re-run it now that the Vue-rendered
        // layout (.app-sidebar, treeview) exists.
        initializeAdminLte();
    });
}
