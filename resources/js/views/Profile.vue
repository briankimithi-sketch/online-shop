<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import { VContainer, VCard, VCardTitle, VCardText, VCardActions, VTextField, VBtn, VAlert, VDivider, VTabs, VTab, VTabsWindow, VTabsWindowItem } from 'vuetify/components';

const auth = useAuthStore();

const profileForm = ref({
  name: '',
  email: '',
  phone: '',
  delivery_address: '',
});

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const activeTab = ref('profile');
const loading = ref(false);
const message = ref('');
const error = ref('');

onMounted(() => {
  if (auth.user) {
    profileForm.value = {
      name: auth.user.name || '',
      email: auth.user.email || '',
      phone: auth.user.phone || '',
      delivery_address: auth.user.delivery_address || '',
    };
  }
});

const updateProfile = async () => {
  loading.value = true;
  error.value = '';
  message.value = '';

  try {
    const response = await axios.put('/api/user/profile', profileForm.value);
    auth.user = { ...auth.user, ...response.data.user };
    message.value = 'Profile updated successfully';
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to update profile';
  } finally {
    loading.value = false;
  }
};

const updatePassword = async () => {
  if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
    error.value = 'Passwords do not match';
    return;
  }

  loading.value = true;
  error.value = '';
  message.value = '';

  try {
    await axios.post('/api/user/password', passwordForm.value);
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' };
    message.value = 'Password updated successfully';
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to update password';
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Profile</h1>

    <v-alert v-if="message" type="success" dismissible>{{ message }}</v-alert>
    <v-alert v-if="error" type="error" dismissible>{{ error }}</v-alert>

    <v-tabs v-model="activeTab" class="mb-4">
      <v-tab value="profile">Profile</v-tab>
      <v-tab value="password">Change Password</v-tab>
    </v-tabs>

    <v-tabs-window v-model="activeTab">
      <v-tabs-window-item value="profile">
      <v-card>
        <v-card-title>Profile Information</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="profileForm.name"
            label="Full Name"
            required
            density="comfortable"
          />
          <v-text-field
            v-model="profileForm.email"
            label="Email"
            type="email"
            required
            density="comfortable"
            readonly
            hint="Email cannot be changed"
          />
          <v-text-field
            v-model="profileForm.phone"
            label="Phone Number"
            prefix="+"
            required
            density="comfortable"
          />
          <v-text-field
            v-model="profileForm.delivery_address"
            label="Default Delivery Address"
            multi-line
            rows="3"
            density="comfortable"
          />
        </v-card-text>
        <v-card-actions>
          <v-btn color="primary" @click="updateProfile" :disabled="loading" :loading="loading">
            Save Changes
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-tabs-window-item>

    <v-tabs-window-item value="password">
      <v-card>
        <v-card-title>Change Password</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="passwordForm.current_password"
            label="Current Password"
            type="password"
            required
            density="comfortable"
          />
          <v-text-field
            v-model="passwordForm.password"
            label="New Password"
            type="password"
            required
            density="comfortable"
          />
          <v-text-field
            v-model="passwordForm.password_confirmation"
            label="Confirm New Password"
            type="password"
            required
            density="comfortable"
          />
        </v-card-text>
        <v-card-actions>
          <v-btn color="primary" @click="updatePassword" :disabled="loading" :loading="loading">
            Update Password
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-tabs-window-item>
  </v-tabs-window>
  </v-container>
</template>