<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { VContainer, VCard, VCardTitle, VCardText, VCardActions, VBtn, VIcon, VDataTable, VTextField, VDialog, VAlert, VSelect, VDivider } from 'vuetify/components';

const router = useRouter();
const products = ref([]);
const loading = ref(true);
const dialog = ref(false);
const deleteDialog = ref(false);
const editing = ref(null);
const deleting = ref(null);
const error = ref('');
const message = ref('');

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Description', key: 'description' },
  { title: 'Price', key: 'price' },
  { title: 'Stock', key: 'stock' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
];

const form = ref({
  name: '',
  description: '',
  price: 0,
  stock: 0,
  status: 'active',
});

onMounted(loadProducts);

const loadProducts = async () => {
  try {
    const response = await axios.get('/api/products');
    products.value = response.data;
  } catch (err) {
    error.value = 'Failed to load products';
  } finally {
    loading.value = false;
  }
};

const openCreate = () => {
  editing.value = null;
  form.value = { name: '', description: '', price: 0, stock: 0, status: 'active' };
  dialog.value = true;
};

const openEdit = (product) => {
  editing.value = product;
  form.value = { ...product, price: product.price / 100 };
  dialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  editing.value = null;
};

const saveProduct = async () => {
  loading.value = true;
  error.value = '';

  try {
    const data = { ...form.value, price: Math.round(form.value.price * 100) };

    if (editing.value) {
      await axios.put(`/api/products/${editing.value.id}`, data);
      message.value = 'Product updated successfully';
    } else {
      await axios.post('/api/products', data);
      message.value = 'Product created successfully';
    }

    dialog.value = false;
    await loadProducts();
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save product';
  } finally {
    loading.value = false;
  }
};

const confirmDelete = (product) => {
  deleting.value = product;
  deleteDialog.value = true;
};

const deleteProduct = async () => {
  if (!deleting.value) return;

  loading.value = true;
  try {
    await axios.delete(`/api/products/${deleting.value.id}`);
    message.value = 'Product deleted successfully';
    await loadProducts();
  } catch (err) {
    error.value = 'Failed to delete product';
  } finally {
    loading.value = false;
    deleteDialog.value = false;
    deleting.value = null;
  }
};

const formatPrice = (price) => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(price / 100);
</script>

<template>
  <v-container>
    <div class="d-flex justify-space-between align-center mb-4">
      <h1 class="text-h4 mb-0">Manage Products</h1>
      <v-btn color="primary" @click="openCreate">
        <v-icon start>mdi-plus</v-icon>
        Add Product
      </v-btn>
    </div>

    <v-alert v-if="message" type="success" dismissible>{{ message }}</v-alert>
    <v-alert v-if="error" type="error" dismissible>{{ error }}</v-alert>

    <v-data-table :items="products" :headers="headers" :loading="loading">
      <template #[`item.price`]="{ item }">
        {{ formatPrice(item.price) }}
      </template>
      <template #[`item.status`]="{ item }">
        <v-chip :color="item.status === 'active' ? 'success' : 'error'" size="small" variant="tonal">
          {{ item.status }}
        </v-chip>
      </template>
      <template #[`item.actions`]="{ item }">
        <v-btn icon variant="text" @click="openEdit(item)">
          <v-icon>mdi-pencil</v-icon>
        </v-btn>
        <v-btn icon variant="text" color="error" @click="confirmDelete(item)">
          <v-icon>mdi-delete</v-icon>
        </v-btn>
      </template>
    </v-data-table>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="600">
      <v-card>
        <v-card-title>{{ editing ? 'Edit Product' : 'Add Product' }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="form.name" label="Name" required density="comfortable" />
          <v-text-field v-model="form.description" label="Description" multi-line rows="3" density="comfortable" />
          <v-text-field v-model="form.price" label="Price (KES)" type="number" required density="comfortable" />
          <v-text-field v-model="form.stock" label="Stock" type="number" required density="comfortable" />
          <v-select v-model="form.status" :items="['active', 'inactive']" label="Status" density="comfortable" />
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveProduct" :disabled="loading" :loading="loading">
            {{ editing ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirm Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Delete Product</v-card-title>
        <v-card-text>
          Are you sure you want to delete "{{ deleting?.name }}"? This action cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="deleteProduct" :disabled="loading" :loading="loading">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>