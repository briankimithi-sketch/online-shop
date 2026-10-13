<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { VContainer, VCard, VCardTitle, VCardText, VRow, VCol, VChip, VBtn, VIcon } from 'vuetify/components';

const stats = ref({
  total_users: 0,
  total_products: 0,
  total_orders: 0,
  total_payments: 0,
  pending_payments: 0,
  paid_payments: 0,
  failed_payments: 0,
  total_revenue: 0,
});
const loading = ref(true);

onMounted(async () => {
  try {
    const response = await axios.get('/api/admin/dashboard');
    stats.value = response.data;
  } catch (err) {
    console.error('Failed to load dashboard:', err);
  } finally {
    loading.value = false;
  }
});

const formatPrice = (price) => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(price / 100);

const statCards = computed(() => [
  { title: 'Total Users', value: stats.value.total_users, icon: 'mdi-account-group', color: 'primary' },
  { title: 'Total Products', value: stats.value.total_products, icon: 'mdi-package-variant', color: 'secondary' },
  { title: 'Total Orders', value: stats.value.total_orders, icon: 'mdi-cart', color: 'info' },
  { title: 'Total Payments', value: stats.value.total_payments, icon: 'mdi-cash', color: 'success' },
  { title: 'Pending Payments', value: stats.value.pending_payments, icon: 'mdi-clock', color: 'warning' },
  { title: 'Failed Payments', value: stats.value.failed_payments, icon: 'mdi-close-circle', color: 'error' },
  { title: 'Total Revenue', value: formatPrice(stats.value.total_revenue), icon: 'mdi-currency-ken', color: 'primary' },
]);
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Admin Dashboard</h1>

    <v-skeleton-loader v-if="loading" type="card" :cols="4" :items="7" />

    <v-row v-else>
      <v-col cols="12" sm="6" md="4" lg="3" v-for="card in statCards" :key="card.title">
        <v-card class="fill-height">
          <v-card-text>
            <div class="d-flex justify-space-between">
              <v-icon :color="card.color" size="large">{{ card.icon }}</v-icon>
              <v-chip :color="card.color" size="small" variant="tonal">{{ card.value }}</v-chip>
            </div>
            <div class="text-h5 mt-2">{{ card.value }}</div>
            <div class="text-caption text-secondary">{{ card.title }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mt-8">
      <v-col cols="12" md="4" class="d-flex">
        <v-card class="w-100 fill-height" to="/admin/products">
          <v-card-title>Manage Products</v-card-title>
          <v-card-text>Add, edit, or remove products</v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4" class="d-flex">
        <v-card class="w-100 fill-height" to="/admin/orders">
          <v-card-title>Manage Orders</v-card-title>
          <v-card-text>View and update order status</v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4" class="d-flex">
        <v-card class="w-100 fill-height" to="/admin/users">
          <v-card-title>Manage Users</v-card-title>
          <v-card-text>View and manage user accounts</v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>