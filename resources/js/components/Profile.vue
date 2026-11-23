<template>
  <div class="row">
    <div class="col-lg-4">
      <div class="card card-primary card-outline mb-4">
        <div class="card-body text-center">
          <span class="user-avatar user-avatar-lg mb-3" aria-hidden="true">{{ initials }}</span>
          <h3 class="h5 mb-0">{{ profile.name || user.name }}</h3>
          <p class="text-secondary text-capitalize mb-3">{{ profile.type || user.type }}</p>
          <ul class="list-group list-group-flush text-start">
            <li class="list-group-item d-flex justify-content-between">
              <span class="fw-semibold">Email</span>
              <span class="text-break ms-2">{{ profile.email || user.email }}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between">
              <span class="fw-semibold">Email verified</span>
              <span>{{ profile.email_verified_at ? formatDate(profile.email_verified_at) : 'No' }}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between">
              <span class="fw-semibold">Member since</span>
              <span>{{ formatDate(profile.created_at) }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Profile details</h3></div>
        <form novalidate @submit.prevent="updateProfile">
          <div class="card-body">
            <div class="mb-3">
              <label for="profile_name" class="form-label">Name</label>
              <input id="profile_name" v-model="profileForm.name" type="text" maxlength="191" autocomplete="name" class="form-control" :class="{ 'is-invalid': profileForm.errors.has('name') }">
              <has-error :form="profileForm" field="name" />
            </div>
            <div class="mb-0">
              <label for="profile_email" class="form-label">Email</label>
              <input id="profile_email" v-model="profileForm.email" type="email" maxlength="191" autocomplete="email" class="form-control" :class="{ 'is-invalid': profileForm.errors.has('email') }">
              <has-error :form="profileForm" field="email" />
            </div>
          </div>
          <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary" :disabled="profileForm.busy">Save profile</button>
          </div>
        </form>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Change password</h3></div>
        <form novalidate @submit.prevent="changePassword">
          <div class="card-body">
            <!-- Hidden username field helps password managers associate the change. -->
            <input type="text" class="d-none" autocomplete="username" :value="profileForm.email" readonly tabindex="-1" aria-hidden="true">
            <div class="mb-3">
              <label for="current_password" class="form-label">Current password</label>
              <input id="current_password" v-model="passwordForm.current_password" type="password" autocomplete="current-password" class="form-control" :class="{ 'is-invalid': passwordForm.errors.has('current_password') }">
              <has-error :form="passwordForm" field="current_password" />
            </div>
            <div class="mb-3">
              <label for="new_password" class="form-label">New password</label>
              <input id="new_password" v-model="passwordForm.new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" class="form-control" :class="{ 'is-invalid': passwordForm.errors.has('new_password') }" aria-describedby="new_password_help">
              <div id="new_password_help" class="form-text">At least 12 characters and different from your current password.</div>
              <has-error :form="passwordForm" field="new_password" />
            </div>
            <div class="mb-0">
              <label for="confirm_password" class="form-label">Confirm new password</label>
              <input id="confirm_password" v-model="passwordForm.confirm_password" type="password" autocomplete="new-password" class="form-control" :class="{ 'is-invalid': passwordForm.errors.has('confirm_password') }">
              <has-error :form="passwordForm" field="confirm_password" />
            </div>
          </div>
          <div class="card-footer text-end">
            <button type="submit" class="btn btn-warning" :disabled="passwordForm.busy">Change password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { Form } from '../lib/Form';
import { track } from '../lib/progress';
import { currentUser, updateCurrentUser } from '../lib/user';
import { errorMessage, toastError, toastSuccess } from '../lib/notify';

export default {
    data() {
        return {
            user: currentUser,
            profile: {},
            profileForm: new Form({ name: currentUser.name || '', email: currentUser.email || '' }),
            passwordForm: new Form({ current_password: '', new_password: '', confirm_password: '' }),
        };
    },
    computed: {
        initials() {
            const name = this.profile.name || this.user.name || '?';
            return name.split(/\s+/).filter(Boolean).slice(0, 2).map(p => p.charAt(0).toUpperCase()).join('') || '?';
        },
    },
    created() {
        this.load();
    },
    methods: {
        async load() {
            try {
                const { data } = await track(axios.get('/api/profile'));
                this.profile = data.data || {};
                this.profileForm.fill(this.profile);
            } catch (error) {
                toastError(errorMessage(error, 'Unable to load your profile.'));
            }
        },
        async updateProfile() {
            try {
                const { data } = await track(this.profileForm.put('/api/profile'));
                this.profile = data.data || this.profile;
                updateCurrentUser(this.profile);
                toastSuccess(data.message);
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
        async changePassword() {
            try {
                const { data } = await track(this.passwordForm.post('/api/change-password'));
                this.passwordForm.reset();
                toastSuccess(data.message);
            } catch (error) {
                toastError(errorMessage(error));
            }
        },
    },
};
</script>
