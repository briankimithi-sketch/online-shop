<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useCartStore } from '@/stores/cart';
import { VCard, VCardTitle, VCardText, VCardActions, VBtn, VIcon, VRow, VCol, VChip, VContainer } from 'vuetify/components';

const cart = useCartStore();
const products = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const response = await axios.get('/api/products');
    products.value = response.data;
  } catch (error) {
    console.error('Failed to fetch products:', error);
  } finally {
    loading.value = false;
  }
});

const addToCart = async (product) => {
  await cart.addItem(product);
};
</script>

<template>
  <v-container>
    <div class="d-flex justify-space-between align-center mb-4">
      <h1 class="text-h4 mb-0">Products</h1>
    </div>

    <v-skeleton-loader v-if="loading" type="card, card, card, card" :cols="4" />

    <v-row v-else>
      <v-col cols="12" sm="6" md="4" lg="3" v-for="product in products" :key="product.id">
        <v-card class="fill-height">
          <v-card-title>{{ product.name }}</v-card-title>
          <v-card-text>
            <p class="text-body-2">{{ product.description }}</p>
            <div class="d-flex justify-space-between align-center mt-2">
              <v-chip color="primary" size="small">
                {{ new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(product.price / 100) }}
              </v-chip>
              <v-chip v-if="product.stock > 0" color="success" size="small">In Stock</v-chip>
              <v-chip v-else color="error" size="small">Out of Stock</v-chip>
            </div>
          </v-card-text>
          <v-card-actions>
            <v-btn color="primary" @click="addToCart(product)" :disabled="product.stock === 0">
              <v-icon start>mdi-cart-plus</v-icon>
              Add to Cart
            </v-btn>
            <v-btn color="secondary" :to="`/products/${product.id}`">
              View
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="!loading && products.length === 0" class="text-center pa-8">
      <v-icon size="x-large" class="mb-4">mdi-package-variant-closed</v-icon>
      <p class="text-h6">No products available</p>
    </div>
  </v-container>
</template>