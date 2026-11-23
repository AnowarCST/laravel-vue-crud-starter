import Swal from 'sweetalert2';

// NOTE: SweetAlert2 renders `title`/`html` as HTML. Any text that may contain
// server or user supplied content must go through `titleText`/`text`, which
// are assigned as textContent.
export const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
});

export function toastSuccess(message) {
    return Toast.fire({ icon: 'success', titleText: String(message || 'Saved') });
}

export function toastError(message) {
    return Toast.fire({ icon: 'error', titleText: String(message || 'Something went wrong. Please try again.') });
}

/** Human readable message for an axios error (never HTML). */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
    const status = error?.response?.status;
    if (status === 422) return error.response.data?.message || 'Please correct the highlighted fields.';
    if (status === 403) return 'You are not allowed to perform this action.';
    if (status === 404) return 'The requested record was not found.';
    if (status === 429) return 'Too many requests. Please wait a moment and try again.';
    return error?.response?.data?.message || fallback;
}

export async function confirmDelete(what = 'this record') {
    const result = await Swal.fire({
        icon: 'warning',
        titleText: `Delete ${what}?`,
        text: "You won't be able to revert this.",
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        confirmButtonColor: '#dc3545',
        focusCancel: true,
    });
    return result.isConfirmed;
}

export { Swal };
