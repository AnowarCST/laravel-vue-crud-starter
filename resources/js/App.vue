<template>
  <div class="app-wrapper">
    <div v-show="progress.pending > 0" class="request-progress" role="progressbar" aria-label="Loading"></div>

    <!-- Header -->
    <nav class="app-header navbar navbar-expand bg-body">
      <div class="container-fluid">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar">
              <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </a>
          </li>
          <li class="nav-item d-none d-md-block">
            <router-link to="/dashboard" class="nav-link">Home</router-link>
          </li>
        </ul>

        <ul class="navbar-nav ms-auto">
          <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="user-avatar user-avatar-sm me-2" aria-hidden="true">{{ initials }}</span>
              <span class="d-none d-md-inline">{{ user.name }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
              <li class="user-header text-bg-primary">
                <span class="user-avatar user-avatar-lg mx-auto mb-2" aria-hidden="true">{{ initials }}</span>
                <p class="mb-0">
                  {{ user.name }}
                  <small class="text-capitalize">{{ user.type }}</small>
                </p>
              </li>
              <li class="user-footer">
                <router-link to="/profile" class="btn btn-outline-secondary">Profile</router-link>
                <button type="button" class="btn btn-outline-secondary float-end" @click="logout">Sign out</button>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </nav>

    <!-- Sidebar -->
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
      <div class="sidebar-brand">
        <router-link to="/dashboard" class="brand-link">
          <img src="/images/logo.png" alt="" class="brand-image opacity-75 shadow">
          <span class="brand-text fw-light">{{ appName }}</span>
        </router-link>
      </div>

      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <ul id="navigation" class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false" role="navigation" aria-label="Main navigation">
            <li class="nav-item">
              <router-link to="/dashboard" class="nav-link" active-class="active">
                <i class="nav-icon fa-solid fa-gauge" aria-hidden="true"></i>
                <p>Dashboard</p>
              </router-link>
            </li>

            <li v-if="$gate.isAdmin()" class="nav-item">
              <router-link to="/users" class="nav-link" active-class="active">
                <i class="nav-icon fa-solid fa-users" aria-hidden="true"></i>
                <p>Users</p>
              </router-link>
            </li>

            <!-- Static `menu-open`: AdminLTE's Treeview owns this class after mount. -->
            <li class="nav-item menu-open">
              <a href="#" class="nav-link" :class="{ active: route.meta.section === 'product' }">
                <i class="nav-icon fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                <p>
                  Catalogue
                  <i class="nav-arrow fa-solid fa-angle-right" aria-hidden="true"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <router-link to="/products" class="nav-link" active-class="active">
                    <i class="nav-icon fa-solid fa-box" aria-hidden="true"></i>
                    <p>Products</p>
                  </router-link>
                </li>
                <li v-if="$gate.isAdmin()" class="nav-item">
                  <router-link to="/product/category" class="nav-link" active-class="active">
                    <i class="nav-icon fa-solid fa-layer-group" aria-hidden="true"></i>
                    <p>Categories</p>
                  </router-link>
                </li>
                <li v-if="$gate.isAdmin()" class="nav-item">
                  <router-link to="/product/tag" class="nav-link" active-class="active">
                    <i class="nav-icon fa-solid fa-tags" aria-hidden="true"></i>
                    <p>Tags</p>
                  </router-link>
                </li>
              </ul>
            </li>

            <li class="nav-header">ACCOUNT</li>
            <li class="nav-item">
              <router-link to="/profile" class="nav-link" active-class="active">
                <i class="nav-icon fa-solid fa-circle-user" aria-hidden="true"></i>
                <p>Profile</p>
              </router-link>
            </li>
            <li class="nav-item">
              <router-link to="/developer" class="nav-link" active-class="active">
                <i class="nav-icon fa-solid fa-code" aria-hidden="true"></i>
                <p>Developer</p>
              </router-link>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link" role="button" @click.prevent="logout">
                <i class="nav-icon fa-solid fa-right-from-bracket text-danger" aria-hidden="true"></i>
                <p>Logout</p>
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </aside>

    <!-- Main -->
    <main id="main" class="app-main" tabindex="-1">
      <div class="app-content-header">
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-6">
              <h3 class="mb-0">{{ title }}</h3>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-end">
                <li class="breadcrumb-item"><router-link to="/dashboard">Home</router-link></li>
                <li class="breadcrumb-item active" aria-current="page">{{ title }}</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <div class="app-content">
        <div class="container-fluid">
          <not-found v-if="forbidden" />
          <router-view v-else />
        </div>
      </div>
    </main>

    <footer class="app-footer">
      <div class="float-end d-none d-sm-inline">
        <template v-if="version">Version {{ version }}</template>
      </div>
      <strong>{{ appName }}</strong> &middot; Laravel + Vue CRUD starter
    </footer>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { PushMenu } from 'admin-lte';
import NotFound from './components/NotFound.vue';
import { currentUser as user } from './lib/user';
import { progress } from './lib/progress';

const route = useRoute();
const { proxy } = getCurrentInstance();

const appName = document.querySelector('meta[name="application-name"]')?.getAttribute('content') || 'Laravel Vue CRUD';
const version = ref('');

const initials = computed(() => (user.name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part.charAt(0).toUpperCase())
    .join('') || '?');
const forbidden = computed(() => Boolean(route.meta.admin) && !proxy.$gate.isAdmin());
const title = computed(() => (forbidden.value ? 'Page not found' : route.meta.title) || appName);

watch(title, value => { document.title = `${value} · ${appName}`; }, { immediate: true });

// On small screens close the off-canvas sidebar after navigating.
watch(() => route.fullPath, () => {
    const sidebar = document.querySelector('.app-sidebar');
    if (sidebar && document.body.classList.contains('sidebar-open')) {
        PushMenu.getOrCreateInstance(sidebar).collapse();
    }
});

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/version');
        version.value = typeof data?.version === 'string' ? data.version : '';
    } catch {
        version.value = '';
    }
});

function logout() {
    // The form lives in the Blade layout (outside the Vue root) and carries @csrf.
    document.getElementById('logout-form')?.requestSubmit();
}
</script>
