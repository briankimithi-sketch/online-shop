<script setup>
import { ref, onMounted, computed } from 'vue';
import { defineProps } from 'vue';
import { useCartStore } from '@/stores/cart';
import axios from 'axios';
import { VContainer, VCard, VCardTitle, VCardText, VCardActions, VBtn, VIcon, VChip, VDivider, VAlert, VRating, VRow, VCol, VTextField } from 'vuetify/components';

const props = defineProps({ id: { type: [String, Number], required: true } });
const cart = useCartStore();

const product = ref(null);
const loading = ref(true);
const error = ref('');
const quantity = ref(1);

onMounted(async () => {
  try {
    const response = await axios.get(`/api/products/${props.id}`);
    product.value = response.data;
  } catch (err) {
    error.value = 'Product not found';
  } finally {
    loading.value = false;
  }
});

const formatPrice = (price) => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(price / 100);

const addToCart = async () => {
  if (!product.value) return;
  await cart.addItem(product.value, quantity.value);
};

const inStock = computed(() => product.value && product.value.stock > 0);
</script>

<template>
  <v-container>
    <div v-if="loading" class="text-center py-12">
      <v-progress-circular indeterminate size="64" color="primary" />
    </div>

    <v-alert v-else-if="error" type="error" dismissible>{{ error }}</v-alert>

    <div v-else-if="product">
      <v-row>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-text class="pa-0">
              <div class="bg-surface-variant d-flex align-center justify-center" style="height: 400px;">
                <v-icon size="x-large" class="text-secondary">mdi-image</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card class="fill-height">
            <v-card-title>{{ product.name }}</v-card-title>
            <v-card-text>
              <v-chip :color="product.stock > 0 ? 'success' : 'error'" size="small">
                {{ product.stock > 0 ? 'In Stock' : 'Out of Stock' }}
              </v-chip>

              <p class="mt-4 text-body-1">{{ product.description }}</p>

              <v-divider class="my-4" />

              <div class="d-flex align-center gap-4 mb-4">
                <span class="text-h5">{{ formatPrice(product.price) }}</span>
              </div>

              <div class="d-flex align-center gap-4 mb-4">
                <label class="text-body-2">Quantity:</label>
                <v-text-field
                  v-model="quantity"
                  type="number"
                  min="1"
                  :max="product.stock"
                  density="compact"
                  variant="outlined"
                  style="width: 100px"
                />
              </div>

              <v-card-actions>
                <v-btn
                  color="primary"
                  size="large"
                  block
                  @click="addToCart"
                  :disabled="!inStock || loading"
                >
                  <v-icon start>mdi-cart-plus</v-icon>
                  Add to Cart
                </v-btn>
              </v-card-actions>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-row class="mt-8">
        <v-col cols="12">
          <v-card>
            <v-card-title>Product Details</v-card-title>
            <v-card-text>
              <div class="text-body-1 white-space-pre-wrap">{{ product.description }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </div>
  </v-container>
</template>