<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { VContainer, VCard, VCardTitle, VCardText, VCardActions, VBtn, VIcon, VDataTable, VDivider, VChip, VAlert } from 'vuetify/components';

const router = useRouter();
const orders = ref([]);
const loading = ref(true);
const error = ref('');

const headers = [
  { title: 'Order #', key: 'id' },
  { title: 'Date', key: 'created_at' },
  { title: 'Status', key: 'status' },
  { title: 'Total', key: 'total' },
  { title: 'Payment', key: 'payment_status' },
  { title: 'Actions', key: 'actions', sortable: false },
];

onMounted(async () => {
  try {
    const response = await axios.get('/api/orders');
    orders.value = response.data;
  } catch (err) {
    error.value = 'Failed to load orders';
  } finally {
    loading.value = false;
  }
});

const formatDate = (date) => new Date(date).toLocaleDateString('en-KE', {
  year: 'numeric',
  month: 'short',
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

const viewOrder = (order) => {
  router.push(`/orders/${order.id}`);
};
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">My Orders</h1>

    <v-alert v-if="error" type="error" dismissible>{{ error }}</v-alert>

    <div v-if="loading" class="text-center py-12">
      <v-progress-circular indeterminate size="64" color="primary" />
    </div>

    <v-card v-else-if="orders.length === 0" class="pa-12 text-center">
      <v-icon size="x-large" class="mb-4">mdi-package-variant-closed</v-icon>
      <h3 class="mb-2">No orders yet</h3>
      <p class="text-secondary mb-6">You haven't placed any orders yet.</p>
      <v-btn color="primary" to="/products">
        <v-icon start>mdi-shop</v-icon>
        Start Shopping
      </v-btn>
    </v-card>

    <v-data-table v-else :items="orders" :headers="headers" :loading="loading">
      <template #[`item.id`]="{ item }">
        <span class="font-weight-medium">#{{ item.id }}</span>
      </template>
      <template #[`item.created_at`]="{ item }">
        {{ formatDate(item.created_at) }}
      </template>
      <template #[`item.status`]="{ item }">
        <v-chip :color="getStatusColor(item.status)" size="small" variant="tonal">
          {{ item.status }}
        </v-chip>
      </template>
      <template #[`item.total`]="{ item }">
        {{ formatPrice(item.total) }}
      </template>
      <template #[`item.payment_status`]="{ item }">
        <v-chip :color="getPaymentColor(item.payment_status)" size="small" variant="tonal">
          {{ item.payment_status }}
        </v-chip>
      </template>
      <template #[`item.actions`]="{ item }">
        <v-btn icon variant="text" @click="viewOrder(item)">
          <v-icon>mdi-eye</v-icon>
        </v-btn>
      </template>
    </v-data-table>
  </v-container>
</template>