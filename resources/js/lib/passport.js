import { errorMessage } from './notify';

/**
 * Passport 13 only registers its JSON API (/oauth/clients, /oauth/tokens,
 * /oauth/personal-access-tokens, /oauth/scopes) when
 * `Passport::$registersJsonApiRoutes = true`. Translate a missing endpoint
 * into a clear message instead of a broken UI.
 */
export const UNAVAILABLE_MESSAGE = 'This Passport API is not available on this server. Use php artisan passport:client to manage OAuth clients from the command line.';

export function describePassportError(error, fallback) {
    const status = error?.response?.status;
    if (status === 404 || status === 405) return { unavailable: true, message: UNAVAILABLE_MESSAGE };
    return { unavailable: false, message: errorMessage(error, fallback) };
}

/** Normalise a 422 response into Form-compatible `{ field: [messages] }`. */
export function validationErrors(error) {
    return error?.response?.status === 422 ? error.response.data?.errors || {} : {};
}
