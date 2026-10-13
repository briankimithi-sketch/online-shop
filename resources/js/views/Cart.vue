<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/stores/cart';
import { useRouter } from 'vue-router';
import { VCard, VCardTitle, VCardText, VCardActions, VBtn, VIcon, VRow, VCol, VTextField, VDivider, VContainer } from 'vuetify/components';

const cart = useCartStore();
const router = useRouter();
const updating = ref(null);

const subtotal = computed(() => cart.total);
const shipping = computed(() => subtotal.value > 5000 ? 0 : 500); // Free shipping over KES 50
const total = computed(() => subtotal.value + shipping.value);

const formatPrice = (price) => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(price / 100);

const updateQuantity = async (item, quantity) => {
  updating.value = item.id;
  await cart.updateQuantity(item.id, quantity);
  updating.value = null;
};

const removeItem = async (item) => {
  await cart.removeItem(item.id);
};

const proceedToCheckout = () => {
  if (cart.items.length > 0) {
    router.push('/checkout');
  }
};
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Shopping Cart</h1>

    <div v-if="cart.items.length === 0" class="text-center pa-12">
      <v-icon size="x-large" class="mb-4">mdi-cart-outline</v-icon>
      <h3 class="mb-2">Your cart is empty</h3>
      <p class="text-secondary mb-6">Looks like you haven't added any items yet.</p>
      <v-btn color="primary" to="/products">
        <v-icon start>mdi-shop</v-icon>
        Continue Shopping
      </v-btn>
    </div>

    <v-row v-else>
      <v-col cols="12" lg="8">
        <v-card v-for="item in cart.items" :key="item.id" class="mb-4">
          <v-card-text>
            <div class="d-flex gap-4">
              <div class="text-h6">{{ item.name }}</div>
              <div class="ms-auto">
                {{ formatPrice(item.price) }}
              </div>
            </div>
            <div class="d-flex align-center gap-4 mt-2">
              <v-text-field
                type="number"
                :model-value="item.quantity"
                @update:model-value="(val) => updateQuantity(item, parseInt(val) || 1)"
                :disabled="updating === item.id"
                min="1"
                :max="item.stock"
                density="compact"
                hide-details
                variant="outlined"
                style="width: 80px"
              />
              <div class="text-caption text-secondary">Stock: {{ item.stock }}</div>
              <v-btn icon color="error" @click="removeItem(item)" class="ms-auto">
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
            <div class="d-flex justify-end mt-2">
              <span class="text-h6">{{ formatPrice(item.price * item.quantity) }}</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="4">
        <v-card class="sticky-top" style="top: 100px;">
          <v-card-title>Order Summary</v-card-title>
          <v-card-text>
            <div class="d-flex justify-space-between mb-2">
              <span>Subtotal</span>
              <span>{{ formatPrice(subtotal) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Shipping</span>
              <span v-if="shipping === 0" class="text-success">Free</span>
              <span v-else>{{ formatPrice(shipping) }}</span>
            </div>
            <v-divider class="my-4" />
            <div class="d-flex justify-space-between text-h6">
              <span>Total</span>
              <span>{{ formatPrice(total) }}</span>
            </div>
          </v-card-text>
          <v-card-actions>
            <v-btn color="primary" block size="large" @click="proceedToCheckout" :disabled="cart.items.length === 0">
              Proceed to Checkout
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>