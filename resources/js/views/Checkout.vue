<script setup>
import { ref, computed, onMounted } from 'vue';
import { useCartStore } from '@/stores/cart';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { VCard, VCardTitle, VCardText, VCardActions, VBtn, VTextField, VDivider, VContainer, VAlert, VSelect, VRadioGroup, VRadio, VProgressLinear } from 'vuetify/components';

const cart = useCartStore();
const router = useRouter();

const form = ref({
  delivery_address: '',
  payment_method: 'mpesa', // 'mpesa' or 'flutterwave'
});

const registeredPhone = ref('');
const profileLoading = ref(true);
const profileError = ref('');

const loading = ref(false);
const error = ref('');
const processing = ref(false);

const subtotal = computed(() => cart.total);
const shipping = computed(() => subtotal.value > 5000 ? 0 : 500);
const total = computed(() => subtotal.value + shipping.value);

const formatPrice = (price) => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(price);

const paymentMethods = [
  { value: 'mpesa', label: 'M-Pesa (STK Push)', icon: 'mdi-cellphone' },
  { value: 'flutterwave', label: 'Flutterwave (Card/Mobile Money)', icon: 'mdi-credit-card' },
];

onMounted(async () => {
  if (cart.items.length === 0) {
    router.push('/cart');
    return;
  }

  try {
    const response = await axios.get('/api/user');
    const user = response.data;

    registeredPhone.value =
      typeof user.phone === 'string' ? user.phone.trim() : '';

    form.value.delivery_address = user.delivery_address || '';

    if (!registeredPhone.value) {
      profileError.value =
        'Your account has no registered phone number. Please update your profile before checkout.';
    }
  } catch (err) {
    profileError.value =
      err.response?.data?.message ||
      'Unable to load your account details. Please sign in again and retry.';
  } finally {
    profileLoading.value = false;
  }
});

const placeOrder = async () => {
  if (!registeredPhone.value) {
    error.value =
      'A registered phone number is required. Please update your profile before checkout.';
    return;
  }

  if (!form.value.delivery_address.trim()) {
    error.value = 'Please enter your delivery address';
    return;
  }

  loading.value = true;
  error.value = '';
  let createdOrderId = null;

  try {
    // Create the order and its single pending payment.
    const orderResponse = await axios.post('/api/orders', {
      items: cart.items.map(item => ({
        product_id: item.id,
        quantity: item.quantity,
      })),
      delivery_address: form.value.delivery_address,
    });

    const order = orderResponse.data.order;
    createdOrderId = order?.id;

    // The backend already created this payment with the order.
    const payment = orderResponse.data.payment;

    // Prevent accidental duplicate orders if provider initiation fails.
    cart.clear();

    if (!payment?.id) {
      throw new Error('The order was created, but its payment record was not returned.');
    }

    // Initiate payment using the payment returned by the order endpoint.
    if (form.value.payment_method === 'mpesa') {
      const mpesaResponse = await axios.post(
        `/api/payments/${payment.id}/mpesa/stk-push`
      );

      if (mpesaResponse.data.success) {
        processing.value = true;
        pollMpesaStatus(payment.id);
      } else {
        error.value = `Order #${createdOrderId} was created, but M-Pesa could not be started: ${
          mpesaResponse.data.message || 'Please try again or contact support.'
        }`;
      }
    } else {
      const fwResponse = await axios.post(
        `/api/payments/${payment.id}/flutterwave/initiate`
      );

      if (fwResponse.data.payment_link) {
        window.location.href = fwResponse.data.payment_link;
      } else {
        error.value = `Order #${createdOrderId} was created, but Flutterwave could not be started: ${
          fwResponse.data.message || 'Please try again or contact support.'
        }`;
      }
    }
  } catch (err) {
    const message =
      err.response?.data?.message ||
      err.message ||
      'An unexpected error occurred';

    error.value = createdOrderId
      ? `Order #${createdOrderId} was created, but payment could not be started: ${message}. Please do not place the order again.`
      : message;

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
              :model-value="registeredPhone"
              label="Registered M-Pesa Number"
              readonly
              :loading="profileLoading"
              hint="This number is taken from your account and cannot be changed during checkout."
              persistent-hint
              density="comfortable"
            />

            <v-alert
              v-if="profileError"
              type="warning"
              variant="tonal"
              class="mb-3"
            >
              {{ profileError }}
            </v-alert>

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
              :disabled="loading || processing || profileLoading || !registeredPhone || cart.items.length === 0"
            >
              {{ processing ? 'Waiting for payment...' : loading ? 'Processing...' : `Place Order - ${formatPrice(total)}` }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-grid>
  </v-container>
</template>