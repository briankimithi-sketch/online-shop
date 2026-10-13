<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { VContainer, VCard, VCardTitle, VCardText, VCardActions, VBtn, VIcon, VAlert } from 'vuetify/components';

const router = useRouter();
const success = ref(true);
const message = ref('');

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search);
  const status = urlParams.get('status');
  const txRef = urlParams.get('tx_ref');

  if (status === 'failed') {
    success.value = false;
    message.value = urlParams.get('message') || 'Payment failed. Please try again.';
  } else if (status === 'cancelled') {
    success.value = false;
    message.value = 'Payment was cancelled.';
  } else {
    message.value = 'Your payment was successful! Thank you for your order.';
  }
});

const goHome = () => router.push('/');
const goOrders = () => router.push('/orders');
</script>

<template>
  <v-container class="text-center pa-12">
    <v-card class="mx-auto" max-width="500">
      <v-card-text class="pa-8">
        <v-icon :color="success ? 'success' : 'error'" size="x-large" class="mb-4">
          {{ success ? 'mdi-check-circle' : 'mdi-close-circle' }}
        </v-icon>

        <h2 class="text-h5 mb-4">{{ success ? 'Payment Successful!' : 'Payment Failed' }}</h2>

        <p class="text-body-1 mb-6">{{ message }}</p>

        <v-alert v-if="success" type="success" variant="tonal" class="mb-4">
          Your order has been confirmed. You will receive a confirmation message shortly.
        </v-alert>

        <v-card-actions class="d-flex gap-4 justify-center">
          <v-btn color="primary" @click="goOrders">
            <v-icon start>mdi-package-variant</v-icon>
            View My Orders
          </v-btn>
          <v-btn variant="outlined" @click="goHome">
            <v-icon start>mdi-home</v-icon>
            Continue Shopping
          </v-btn>
        </v-card-actions>
      </v-card-text>
    </v-card>
  </v-container>
</template>