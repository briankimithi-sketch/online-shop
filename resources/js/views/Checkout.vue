<script setup>
import { ref, computed, onMounted } from 'vue';
import { useCartStore } from '@/stores/cart';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { VCard, VCardTitle, VCardText, VCardActions, VBtn, VTextField, VDivider, VContainer, VAlert, VSelect, VRadioGroup, VRadio, VProgressLinear } from 'vuetify/components';

const cart = useCartStore();
const router = useRouter();

const form = ref({
  phone: '',
  delivery_address: '',
  payment_method: 'mpesa', // 'mpesa' or 'flutterwave'
});

const loading = ref(false);
const error = ref('');
const processing = ref(false);

const subtotal = computed(() => cart.total);
const shipping = computed(() => subtotal.value > 5000 ? 0 : 500);
const total = computed(() => subtotal.value + shipping.value);

const formatPrice = (price) => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(price / 100);

const paymentMethods = [
  { value: 'mpesa', label: 'M-Pesa (STK Push)', icon: 'mdi-cellphone' },
  { value: 'flutterwave', label: 'Flutterwave (Card/Mobile Money)', icon: 'mdi-credit-card' },
];

onMounted(() => {
  if (cart.items.length === 0) {
    router.push('/cart');
  }
  // Pre-fill user data if available
  const auth = JSON.parse(localStorage.getItem('user') || '{}');
  if (auth.phone) form.value.phone = auth.phone;
  if (auth.delivery_address) form.value.delivery_address = auth.delivery_address;
});

const placeOrder = async () => {
  if (!form.value.phone || !form.value.delivery_address) {
    error.value = 'Please fill in all required fields';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    // Create order
    const orderResponse = await axios.post('/api/orders', {
      items: cart.items.map(item => ({
        product_id: item.id,
        quantity: item.quantity,
        price: item.price,
      })),
      delivery_address: form.value.delivery_address,
      phone: form.value.phone,
      total: total.value,
    });

    const order = orderResponse.data.order;

    // Create payment
    const paymentResponse = await axios.post('/api/payments', {
      order_id: order.id,
      amount: total.value,
      payment_status: 'pending',
    });

    const payment = paymentResponse.data.payment;

    // Initiate payment based on method
    if (form.value.payment_method === 'mpesa') {
      const mpesaResponse = await axios.post(`/api/payments/${payment.id}/mpesa/stk-push`);
      if (mpesaResponse.data.success) {
        processing.value = true;
        // Poll for payment status
        pollMpesaStatus(payment.id);
      } else {
        error.value = mpesaResponse.data.message || 'M-Pesa payment failed';
      }
    } else {
      const fwResponse = await axios.post(`/api/payments/${payment.id}/flutterwave/initiate`);
      if (fwResponse.data.payment_link) {
        window.location.href = fwResponse.data.payment_link;
      } else {
        error.value = fwResponse.data.message || 'Flutterwave payment failed';
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to place order';
    console.error(err);
  } finally {
    loading.value = false;
  }
};

const pollMpesaStatus = async (paymentId) => {
  const maxAttempts = 20;
  let attempts = 0;

  const poll = async () => {
    if (attempts >= maxAttempts) {
      processing.value = false;
      error.value = 'Payment timeout. Please check your phone for STK Push.';
      return;
    }

    try {
      const response = await axios.get(`/api/payments/${paymentId}/mpesa/query`);
      if (response.data.is_completed) {
        processing.value = false;
        router.push('/payment/success');
        return;
      }
      if (response.data.is_failed) {
        processing.value = false;
        error.value = response.data.result_desc || 'Payment failed';
        return;
      }
    } catch (err) {
      console.error('Polling error:', err);
    }

    attempts++;
    setTimeout(poll, 5000);
  };

  poll();
};
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Checkout</h1>

    <v-alert v-if="error" type="error" dismissible>{{ error }}</v-alert>

    <v-grid>
      <v-col cols="12" lg="7">
        <v-card class="mb-4">
          <v-card-title>Delivery Information</v-card-title>
          <v-card-text>
            <v-text-field
              v-model="form.phone"
              label="Phone Number"
              placeholder="2547XXXXXXXX"
              required
              prefix="+"
              density="comfortable"
            />
            <v-text-field
              v-model="form.delivery_address"
              label="Delivery Address"
              placeholder="Enter your delivery address"
              required
              multi-line
              rows="3"
              density="comfortable"
            />
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title>Payment Method</v-card-title>
          <v-card-text>
            <v-radio-group v-model="form.payment_method" row>
              <v-radio
                v-for="method in paymentMethods"
                :key="method.value"
                :value="method.value"
                :label="method.label"
              />
            </v-radio-group>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="5">
        <v-card class="sticky-top" style="top: 100px;">
          <v-card-title>Order Summary</v-card-title>
          <v-card-text>
            <div v-for="item in cart.items" :key="item.id" class="d-flex justify-space-between py-2 border-bottom">
              <div>
                <div class="text-body-1">{{ item.name }}</div>
                <div class="text-caption text-secondary">Qty: {{ item.quantity }}</div>
              </div>
              <div class="text-body-1">{{ formatPrice(item.price * item.quantity) }}</div>
            </div>

            <v-divider class="my-4" />

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
            <v-progress-linear v-if="processing" indeterminate color="primary" />
            <v-btn
              color="primary"
              block
              size="large"
              @click="placeOrder"
              :disabled="loading || processing || cart.items.length === 0"
            >
              {{ processing ? 'Waiting for payment...' : loading ? 'Processing...' : `Place Order - ${formatPrice(total)}` }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-grid>
  </v-container>
</template>