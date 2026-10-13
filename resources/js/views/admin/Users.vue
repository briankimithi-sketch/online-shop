<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { VContainer, VCard, VCardTitle, VCardText, VDataTable, VBtn, VIcon, VChip, VDialog, VTextField, VSelect, VAlert } from 'vuetify/components';

const users = ref([]);
const loading = ref(true);
const dialog = ref(false);
const editing = ref(null);
const error = ref('');
const message = ref('');

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Email', key: 'email' },
  { title: 'Phone', key: 'phone' },
  { title: 'Role', key: 'role' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
];

const form = ref({
  name: '',
  email: '',
  phone: '',
  delivery_address: '',
  role_id: 2,
  password: '',
});

const roles = [
  { id: 1, name: 'Admin' },
  { id: 2, name: 'user' },
];

onMounted(loadUsers);

const loadUsers = async () => {
  try {
    const response = await axios.get('/api/admin/users');
    users.value = response.data;
  } catch (err) {
    error.value = 'Failed to load users';
  } finally {
    loading.value = false;
  }
};

const openCreate = () => {
  editing.value = null;
  form.value = { name: '', email: '', phone: '', delivery_address: '', role_id: 2, password: '' };
  dialog.value = true;
};

const openEdit = (user) => {
  editing.value = user;
  form.value = { ...user, password: '' };
  dialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  editing.value = null;
};

const saveUser = async () => {
  loading.value = true;
  error.value = '';

  try {
    const data = { ...form.value };
    if (!data.password) delete data.password;

    if (editing.value) {
      await axios.put(`/api/admin/users/${editing.value.id}`, data);
      message.value = 'User updated successfully';
    } else {
      if (!data.password) {
        error.value = 'Password is required for new users';
        loading.value = false;
        return;
      }
      await axios.post('/api/admin/users', data);
      message.value = 'User created successfully';
    }

    dialog.value = false;
    await loadUsers();
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to save user';
  } finally {
    loading.value = false;
  }
};

const deleteUser = async (user) => {
  if (!confirm(`Delete user "${user.name}"?`)) return;

  loading.value = true;
  try {
    await axios.delete(`/api/admin/users/${user.id}`);
    message.value = 'User deleted successfully';
    await loadUsers();
  } catch (err) {
    error.value = 'Failed to delete user';
  } finally {
    loading.value = false;
  }
};

const getRoleName = (roleId) => roles.find(r => r.id === roleId)?.name || 'Unknown';
</script>

<template>
  <v-container>
    <div class="d-flex justify-space-between align-center mb-4">
      <h1 class="text-h4 mb-0">Manage Users</h1>
      <v-btn color="primary" @click="openCreate">
        <v-icon start>mdi-plus</v-icon>
        Add User
      </v-btn>
    </div>

    <v-alert v-if="message" type="success" dismissible>{{ message }}</v-alert>
    <v-alert v-if="error" type="error" dismissible>{{ error }}</v-alert>

    <v-data-table :items="users" :headers="headers" :loading="loading">
      <template #[`item.role`]="{ item }">
        <v-chip :color="item.role_id === 1 ? 'primary' : 'secondary'" size="small" variant="tonal">
          {{ getRoleName(item.role_id) }}
        </v-chip>
      </template>
      <template #[`item.status`]="{ item }">
        <v-chip color="success" size="small" variant="tonal">Active</v-chip>
      </template>
      <template #[`item.actions`]="{ item }">
        <v-btn icon variant="text" @click="openEdit(item)">
          <v-icon>mdi-pencil</v-icon>
        </v-btn>
        <v-btn icon variant="text" color="error" @click="deleteUser(item)">
          <v-icon>mdi-delete</v-icon>
        </v-btn>
      </template>
    </v-data-table>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="600">
      <v-card>
        <v-card-title>{{ editing ? 'Edit User' : 'Add User' }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="form.name" label="Name" required density="comfortable" />
          <v-text-field v-model="form.email" label="Email" type="email" required density="comfortable" :readonly="!!editing" />
          <v-text-field v-model="form.phone" label="Phone" prefix="+" density="comfortable" />
          <v-text-field v-model="form.delivery_address" label="Delivery Address" multi-line rows="2" density="comfortable" />
          <v-select v-model="form.role_id" :items="roles" item-title="name" item-value="id" label="Role" density="comfortable" />
          <v-text-field
            v-model="form.password"
            :label="editing ? 'New Password (leave blank to keep current)' : 'Password'"
            type="password"
            :required="!editing"
            density="comfortable"
          />
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveUser" :disabled="loading" :loading="loading">
            {{ editing ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>