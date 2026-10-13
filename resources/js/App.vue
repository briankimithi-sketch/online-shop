<script setup>
import { onMounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';

import { RouterView } from 'vue-router';
import { VApp, VAppBar, VAppBarNavIcon, VAppBarTitle, VBtn, VContainer, VList, VListItem, VListItemTitle, VMain, VNavigationDrawer, VSpacer, VToolbar, VDivider } from 'vuetify/components';

const auth = useAuthStore();
const cart = useCartStore();
const drawer = ref(true);

onMounted(async () => {
  await auth.fetchUser();
  await cart.fetchCart();
});

const navigation = [
  { title: 'Home', to: '/' },
  { title: 'Products', to: '/products' },
  { title: 'Cart', to: '/cart', badge: cart.itemCount },
];

const adminNavigation = [
  { title: 'Dashboard', to: '/admin/dashboard' },
  { title: 'Products', to: '/admin/products' },
  { title: 'Orders', to: '/admin/orders' },
  { title: 'Users', to: '/admin/users' },
];

const userNavigation = [
  { title: 'My Orders', to: '/orders' },
  { title: 'Profile', to: '/profile' },
];

const logout = () => {
  auth.logout();
};
</script>

<template>
  <v-app>
    <v-navigation-drawer v-model="drawer" :permanent="true" :width="250" class="bg-surface-variant">
      <v-list lines="two">
        <v-list-item to="/" prepend-icon="mdi-home">
          <v-list-item-title>Home</v-list-item-title>
        </v-list-item>
        <v-list-item to="/products" prepend-icon="mdi-package-variant">
          <v-list-item-title>Products</v-list-item-title>
        </v-list-item>
        <v-list-item to="/cart" prepend-icon="mdi-cart" :append="cart.itemCount > 0 ? String(cart.itemCount) : ''">
          <v-list-item-title>Cart</v-list-item-title>
        </v-list-item>

        <v-divider class="my-2" />

        <template v-if="auth.isAuthenticated">
          <v-list-item-title class="px-3 text-caption text-secondary">ACCOUNT</v-list-item-title>
          <v-list-item v-for="item in userNavigation" :key="item.title" :to="item.to" prepend-icon="mdi-account">
            <v-list-item-title>{{ item.title }}</v-list-item-title>
          </v-list-item>

          <v-list-item-title class="px-3 text-caption text-secondary" v-if="auth.isAdmin">ADMIN</v-list-item-title>
          <v-list-item v-for="item in adminNavigation" :key="item.title" :to="item.to" prepend-icon="mdi-shield-account" v-if="auth.isAdmin">
            <v-list-item-title>{{ item.title }}</v-list-item-title>
          </v-list-item>

          <v-divider class="my-2" />

          <v-list-item @click="logout" prepend-icon="mdi-logout">
            <v-list-item-title>Logout</v-list-item-title>
          </v-list-item>
        </template>

        <template v-else>
          <v-list-item to="/login" prepend-icon="mdi-login">
            <v-list-item-title>Login</v-list-item-title>
          </v-list-item>
          <v-list-item to="/register" prepend-icon="mdi-account-plus">
            <v-list-item-title>Register</v-list-item-title>
          </v-list-item>
        </template>
      </v-list>
    </v-navigation-drawer>

    <v-app-bar app color="primary" dark>
      <v-app-bar-nav-icon @click="drawer = !drawer" />
      <v-app-bar-title>Online Shop</v-app-bar-title>
      <v-spacer />
      <v-btn text to="/cart" v-if="auth.isAuthenticated">
        Cart ({{ cart.itemCount }})
      </v-btn>
    </v-app-bar>

    <v-main class="pa-4">
      <RouterView />
    </v-main>
  </v-app>
</template>