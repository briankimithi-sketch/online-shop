<script setup>
import { ref } from 'vue';
import { VContainer, VRow, VCol, VCard, VCardTitle, VCardText, VBtn, VIcon } from 'vuetify/components';
</script>

<template>
  <v-container>
    <v-row class="mb-8">
      <v-col cols="12" class="text-center">
        <h1 class="text-h3 mb-2">Welcome to Online Shop</h1>
        <p class="text-subtitle-1 text-secondary">Your one-stop shop for quality products</p>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12" md="4" class="d-flex">
        <v-card class="w-100 fill-height">
          <v-card-title>Quality Products</v-card-title>
          <v-card-text>Curated selection of premium products at competitive prices.</v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4" class="d-flex">
        <v-card class="w-100 fill-height">
          <v-card-title>Secure Payments</v-card-title>
          <v-card-text>Multiple payment options including M-Pesa and Flutterwave.</v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4" class="d-flex">
        <v-card class="w-100 fill-height">
          <v-card-title>Fast Delivery</v-card-title>
          <v-card-text>Quick and reliable delivery to your doorstep.</v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mt-8">
      <v-col cols="12" class="text-center">
        <v-btn color="primary" size="large" to="/products">
          <v-icon start>mdi-shop</v-icon>
          Shop Now
        </v-btn>
      </v-col>
    </v-row>
  </v-container>
</template>