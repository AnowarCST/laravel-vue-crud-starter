<template>
    <div v-if="form.errors.has(field)" class="invalid-feedback d-block" role="alert">{{ form.errors.get(field) }}</div>
</template>
<script setup>
defineProps({ form: { type: Object, required: true }, field: { type: String, required: true } });
</script>
