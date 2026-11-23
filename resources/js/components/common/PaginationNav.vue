<template>
    <nav v-if="lastPage > 1" aria-label="Pagination">
        <ul class="pagination pagination-sm m-0 float-end">
            <li class="page-item" :class="{ disabled: current <= 1 }">
                <button
                    type="button"
                    class="page-link"
                    :disabled="current <= 1"
                    aria-label="Previous"
                    @click="go(current - 1)"
                >
                    &laquo;
                </button>
            </li>
            <li v-for="page in pages" :key="page" class="page-item" :class="{ active: page === current }">
                <button
                    type="button"
                    class="page-link"
                    :aria-current="page === current ? 'page' : null"
                    @click="go(page)"
                >
                    {{ page }}
                </button>
            </li>
            <li class="page-item" :class="{ disabled: current >= lastPage }">
                <button
                    type="button"
                    class="page-link"
                    :disabled="current >= lastPage"
                    aria-label="Next"
                    @click="go(current + 1)"
                >
                    &raquo;
                </button>
            </li>
        </ul>
    </nav>
</template>

<script setup>
import { computed } from 'vue';

// `data` is a Laravel LengthAwarePaginator JSON payload.
const props = defineProps({ data: { type: Object, default: () => ({}) }, limit: { type: Number, default: 2 } });
const emit = defineEmits(['pagination-change-page']);

const current = computed(() => Number(props.data.current_page) || 1);
const lastPage = computed(() => Number(props.data.last_page) || 1);
const pages = computed(() => {
    const start = Math.max(1, current.value - props.limit);
    const end = Math.min(lastPage.value, current.value + props.limit);
    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
});

function go(page) {
    if (page >= 1 && page <= lastPage.value && page !== current.value) emit('pagination-change-page', page);
}
</script>
