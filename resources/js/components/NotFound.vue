<template>
  <div class="error-page py-5 text-center">
    <h2 class="display-4 text-warning fw-bold">404</h2>
    <h3 class="h4"><i class="fa-solid fa-triangle-exclamation text-warning me-2" aria-hidden="true"></i>Oops! Page not found.</h3>
    <p class="text-secondary">
      The page you are looking for does not exist or you do not have access to it.
    </p>
    <router-link to="/dashboard" class="btn btn-primary">
      <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Back to dashboard
    </router-link>
  </div>
</template>
