import { reactive } from 'vue';

// Replacement for vue-progressbar: a pending-request counter rendered as a
// thin progress bar in App.vue.
export const progress = reactive({ pending: 0 });

/** Wrap a promise so the progress bar is shown while it is pending. */
export async function track(promise) {
    progress.pending += 1;
    try {
        return await promise;
    } finally {
        progress.pending = Math.max(0, progress.pending - 1);
    }
}
