<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { VContainer, VCard, VCardTitle, VCardText, VCardActions, VTextField, VBtn, VAlert, VDivider, VSelect } from 'vuetify/components';

const router = useRouter();
const auth = useAuthStore();

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone: '',
  delivery_address: '',
  role_id: 2, // Default to user
});

const loading = ref(false);
const error = ref('');

const register = async () => {
  if (form.value.password !== form.value.password_confirmation) {
    error.value = 'Passwords do not match';
    return;
  }

  loading.value = true;
  error.value = '';

  const result = await auth.register(form.value);
  if (result.success) {
    router.push('/');
  } else {
    error.value = result.message;
  }
  loading.value = false;
};
</script>

<template>
  <v-container>
    <v-row justify="center">
      <v-col cols="12" sm="8" md="6" lg="4">
        <v-card>
          <v-card-title class="text-h4 text-center">Register</v-card-title>
          <v-card-text>
            <v-alert v-if="error" type="error" dismissible>{{ error }}</v-alert>

            <v-text-field
              v-model="form.name"
              label="Full Name"
              required
              density="comfortable"
              :disabled="loading"
            />
            <v-text-field
              v-model="form.email"
              label="Email"
              type="email"
              required
              density="comfortable"
              :disabled="loading"
            />
            <v-text-field
              v-model="form.phone"
              label="Phone Number"
              placeholder="2547XXXXXXXX"
              prefix="+"
              required
              density="comfortable"
              :disabled="loading"
            />
            <v-text-field
              v-model="form.delivery_address"
              label="Delivery Address"
              multi-line
              rows="2"
              required
              density="comfortable"
              :disabled="loading"
            />
            <v-text-field
              v-model="form.password"
              label="Password"
              type="password"
              required
              density="comfortable"
              :disabled="loading"
            />
            <v-text-field
              v-model="form.password_confirmation"
              label="Confirm Password"
              type="password"
              required
              density="comfortable"
              :disabled="loading"
            />

            <v-card-actions class="mt-4">
              <v-btn color="primary" block @click="register" :disabled="loading" :loading="loading">
                Register
              </v-btn>
            </v-card-actions>

            <v-divider class="my-4">or</v-divider>

            <v-card-actions>
              <v-btn variant="outlined" block to="/login">
                Already have an account? Login
              </v-btn>
            </v-card-actions>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>