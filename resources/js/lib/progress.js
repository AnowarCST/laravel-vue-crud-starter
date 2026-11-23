import { reactive } from 'vue';

// Replacement for vue-progressbar: a pending-request counter rendered as a
// thin progress bar in App.vue.
export const progress = reactive({ pending: 0 });

export const Progress = {
    start() { progress.pending += 1; },
    finish() { progress.pending = Math.max(0, progress.pending - 1); },
    fail() { progress.pending = Math.max(0, progress.pending - 1); },
};

/** Wrap a promise so the progress bar is shown while it is pending. */
export async function track(promise) {
    Progress.start();
    try {
        return await promise;
    } finally {
        Progress.finish();
    }
}
