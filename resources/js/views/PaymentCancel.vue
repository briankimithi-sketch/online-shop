<script setup>
import { useRouter } from 'vue-router';
import { VContainer, VCard, VCardTitle, VCardText, VCardActions, VBtn, VIcon } from 'vuetify/components';

const router = useRouter();
const goCart = () => router.push('/cart');
const goHome = () => router.push('/');
</script>

<template>
  <v-container class="text-center pa-12">
    <v-card class="mx-auto" max-width="500">
      <v-card-text class="pa-8">
        <v-icon color="warning" size="x-large" class="mb-4">mdi-clock-alert</v-icon>

        <h2 class="text-h5 mb-4">Payment Cancelled</h2>

        <p class="text-body-1 mb-6">Your payment was not completed. Your cart has been saved.</p>

        <v-card-actions class="d-flex gap-4 justify-center">
          <v-btn color="primary" @click="goCart">
            <v-icon start>mdi-cart</v-icon>
            Return to Cart
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