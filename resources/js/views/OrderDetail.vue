<script setup>
import { ref, onMounted, computed } from 'vue';
import { defineProps } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { VContainer, VCard, VCardTitle, VCardText, VCardActions, VBtn, VIcon, VDivider, VChip, VAlert, VList, VListItem, VListItemTitle, VListItemSubtitle } from 'vuetify/components';

const props = defineProps({ id: { type: [String, Number], required: true } });
const router = useRouter();

const order = ref(null);
const loading = ref(true);
const error = ref('');

const formatDate = (date) => new Date(date).toLocaleDateString('en-KE', {
  year: 'numeric',
  month: 'long',
  day: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
});

const formatPrice = (price) => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(price / 100);

const getStatusColor = (status) => {
  const colors = {
    pending: 'warning',
    processing: 'info',
    shipped: 'primary',
    delivered: 'success',
    cancelled: 'error',
  };
  return colors[status] || 'default';
};

const getPaymentColor = (status) => {
  const colors = {
    pending: 'warning',
    paid: 'success',
    failed: 'error',
  };
  return colors[status] || 'default';
};

onMounted(async () => {
  try {
    const response = await axios.get(`/api/orders/${props.id}`);
    order.value = response.data;
  } catch (err) {
    error.value = 'Order not found';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <v-container>
    <div v-if="loading" class="text-center py-12">
      <v-progress-circular indeterminate size="64" color="primary" />
    </div>

    <v-alert v-else-if="error" type="error" dismissible>{{ error }}</v-alert>

    <div v-else-if="order">
      <div class="d-flex justify-space-between align-center mb-4">
        <h1 class="text-h4 mb-0">Order #{{ order.id }}</h1>
        <v-chip :color="getStatusColor(order.status)" size="large">
          {{ order.status }}
        </v-chip>
      </div>

      <v-grid>
        <v-col cols="12" lg="8">
          <v-card class="mb-4">
            <v-card-title>Order Items</v-card-title>
            <v-card-text>
              <v-list lines="two" v-for="item in order.items" :key="item.id">
                <v-list-item>
                  <v-list-item-title>{{ item.name }}</v-list-item-title>
                  <v-list-item-subtitle>
                    Qty: {{ item.quantity }} × {{ formatPrice(item.price) }}
                  </v-list-item-subtitle>
                  <template #append>
                    <span class="text-h6">{{ formatPrice(item.price * item.quantity) }}</span>
                  </template>
                </v-list-item>
                <v-divider :inset="true" />
              </v-list>
            </v-card-text>
          </v-card>

          <v-card class="mb-4">
            <v-card-title>Delivery Address</v-card-title>
            <v-card-text class="white-space-pre-wrap">{{ order.delivery_address }}</v-card-text>
          </v-card>

          <v-card class="mb-4">
            <v-card-title>Payment</v-card-title>
            <v-card-text>
              <div class="d-flex justify-space-between">
                <span>Payment Status</span>
                <v-chip :color="getPaymentColor(order.payment?.payment_status)" size="large">
                  {{ order.payment?.payment_status || 'pending' }}
                </v-chip>
              </div>
              <div v-if="order.payment?.flutterwave_tx_ref" class="mt-2 text-caption">
                Flutterwave Ref: {{ order.payment.flutterwave_tx_ref }}
              </div>
              <div v-if="order.payment?.mpesa_checkout_request_id" class="mt-2 text-caption">
                M-Pesa Checkout: {{ order.payment.mpesa_checkout_request_id }}
              </div>
              <div v-if="order.payment?.mpesa_receipt_number" class="mt-2 text-caption text-success">
                M-Pesa Receipt: {{ order.payment.mpesa_receipt_number }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" lg="4">
          <v-card class="sticky-top" style="top: 100px;">
            <v-card-title>Order Summary</v-card-title>
            <v-card-text>
              <v-list lines="two">
                <v-list-item>
                  <v-list-item-title>Subtotal</v-list-item-title>
                  <template #append>{{ formatPrice(order.subtotal || order.total - (order.shipping || 0)) }}</template>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Shipping</v-list-item-title>
                  <template #append>{{ formatPrice(order.shipping || 0) }}</template>
                </v-list-item>
              </v-list>
              <v-divider class="my-4" />
              <v-list>
                <v-list-item class="text-h6">
                  <v-list-item-title>Total</v-list-item-title>
                  <template #append>{{ formatPrice(order.total) }}</template>
                </v-list-item>
              </v-list>
            </v-card-text>
            <v-card-actions>
              <v-btn variant="outlined" block @click="router.go(-1)">
                <v-icon start>mdi-arrow-left</v-icon>
                Back to Orders
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-grid>
    </div>
  </v-container>
</template>