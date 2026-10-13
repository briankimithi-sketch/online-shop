<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { VContainer, VCard, VCardTitle, VCardText, VDataTable, VBtn, VIcon, VChip, VDialog, VSelect, VDivider, VAlert } from 'vuetify/components';

const router = useRouter();
const orders = ref([]);
const loading = ref(true);
const dialog = ref(false);
const selectedOrder = ref(null);
const error = ref('');
const message = ref('');

const headers = [
  { title: 'Order #', key: 'id' },
  { title: 'Customer', key: 'user' },
  { title: 'Date', key: 'created_at' },
  { title: 'Status', key: 'status' },
  { title: 'Payment', key: 'payment_status' },
  { title: 'Total', key: 'total' },
  { title: 'Actions', key: 'actions', sortable: false },
];

const statusOptions = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

onMounted(loadOrders);

const loadOrders = async () => {
  try {
    const response = await axios.get('/api/admin/orders');
    orders.value = response.data;
  } catch (err) {
    error.value = 'Failed to load orders';
  } finally {
    loading.value = false;
  }
};

const openOrder = (order) => {
  selectedOrder.value = { ...order };
  dialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  selectedOrder.value = null;
};

const updateStatus = async () => {
  if (!selectedOrder.value) return;

  loading.value = true;
  try {
    await axios.put(`/api/admin/orders/${selectedOrder.value.id}`, {
      status: selectedOrder.value.status,
    });
    message.value = 'Order status updated';
    await loadOrders();
    dialog.value = false;
  } catch (err) {
    error.value = 'Failed to update status';
  } finally {
    loading.value = false;
  }
};

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
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Manage Orders</h1>

    <v-alert v-if="message" type="success" dismissible>{{ message }}</v-alert>
    <v-alert v-if="error" type="error" dismissible>{{ error }}</v-alert>

    <v-data-table :items="orders" :headers="headers" :loading="loading">
      <template #[`item.user`]="{ item }">
        <div>
          <div class="font-weight-medium">{{ item.user?.name }}</div>
          <div class="text-caption text-secondary">{{ item.user?.email }}</div>
        </div>
      </template>
      <template #[`item.created_at`]="{ item }">
        {{ formatDate(item.created_at) }}
      </template>
      <template #[`item.status`]="{ item }">
        <v-chip :color="getStatusColor(item.status)" size="small" variant="tonal">
          {{ item.status }}
        </v-chip>
      </template>
      <template #[`item.payment_status`]="{ item }">
        <v-chip :color="getPaymentColor(item.payment?.payment_status)" size="small" variant="tonal">
          {{ item.payment?.payment_status || 'pending' }}
        </v-chip>
      </template>
      <template #[`item.total`]="{ item }">
        {{ formatPrice(item.total) }}
      </template>
      <template #[`item.actions`]="{ item }">
        <v-btn icon variant="text" @click="openOrder(item)">
          <v-icon>mdi-pencil</v-icon>
        </v-btn>
      </template>
    </v-data-table>

    <!-- Status Update Dialog -->
    <v-dialog v-model="dialog" max-width="500">
      <v-card>
        <v-card-title>Update Order Status</v-card-title>
        <v-card-text>
          <div class="mb-4">
            <strong>Order #{{ selectedOrder?.id }}</strong>
            <div class="text-caption text-secondary">{{ selectedOrder?.user?.name }} - {{ formatDate(selectedOrder?.created_at) }}</div>
          </div>
          <v-select
            v-model="selectedOrder.status"
            :items="statusOptions"
            label="Order Status"
            density="comfortable"
          />
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" @click="updateStatus" :disabled="loading" :loading="loading">
            Update Status
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>