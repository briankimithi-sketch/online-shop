<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { VContainer, VCard, VCardTitle, VCardText, VCardActions, VTextField, VBtn, VAlert, VDivider } from 'vuetify/components';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

const form = ref({
  email: '',
  password: '',
});

const loading = ref(false);
const error = ref('');

const redirect = route.query.redirect || '/';

const login = async () => {
  loading.value = true;
  error.value = '';

  const result = await auth.login(form.value);
  if (result.success) {
    router.push(redirect);
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
          <v-card-title class="text-h4 text-center">Login</v-card-title>
          <v-card-text>
            <v-alert v-if="error" type="error" dismissible>{{ error }}</v-alert>

            <v-text-field
              v-model="form.email"
              label="Email"
              type="email"
              required
              density="comfortable"
              :disabled="loading"
              @keydown.enter="login"
            />
            <v-text-field
              v-model="form.password"
              label="Password"
              type="password"
              required
              density="comfortable"
              :disabled="loading"
              @keydown.enter="login"
            />

            <v-card-actions class="mt-4">
              <v-btn color="primary" block @click="login" :disabled="loading" :loading="loading">
                Login
              </v-btn>
            </v-card-actions>

            <v-divider class="my-4">or</v-divider>

            <v-card-actions>
              <v-btn variant="outlined" block to="/register">
                Create Account
              </v-btn>
            </v-card-actions>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>