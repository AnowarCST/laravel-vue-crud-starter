<template>
    <div class="row">
        <div class="col-12">
            <div class="callout callout-info mb-4">
                <h5 class="mb-1">OAuth &amp; API access</h5>
                <p class="mb-0">
                    Manage the OAuth clients you own, applications you have authorized and your personal access tokens.
                    These pages use Laravel Passport's JSON API; if it is disabled on this server you can still manage
                    clients with <code>php artisan passport:client</code>.
                </p>
            </div>
            <passport-clients />
            <passport-authorized-clients />
            <passport-personal-access-tokens />
        </div>
    </div>
</template>

<script>
import PassportClients from './passport/PassportClients.vue';
import PassportAuthorizedClients from './passport/AuthorizedClients.vue';
import PassportPersonalAccessTokens from './passport/PersonalAccessTokens.vue';

export default {
    components: { PassportClients, PassportAuthorizedClients, PassportPersonalAccessTokens },
};
</script>
