import { reactive } from 'vue';

/**
 * Current user, provided by the server via
 * <div id="spa-app" data-user="{...}"> (Blade-escaped JSON).
 * Only id, name, email and type are exposed. This is for UI decisions only;
 * every permission is enforced again by the API.
 */
function readUser() {
    const raw = document.getElementById('spa-app')?.dataset.user;
    if (!raw) return {};
    try {
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return {};
        return {
            id: parsed.id ?? null,
            name: typeof parsed.name === 'string' ? parsed.name : '',
            email: typeof parsed.email === 'string' ? parsed.email : '',
            type: typeof parsed.type === 'string' ? parsed.type : '',
        };
    } catch {
        return {};
    }
}

export const currentUser = reactive(readUser());

export function updateCurrentUser(values = {}) {
    for (const key of ['name', 'email']) {
        if (typeof values[key] === 'string') currentUser[key] = values[key];
    }
}
