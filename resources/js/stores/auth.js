import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.role_id === 1,
  },

  actions: {
    setToken(token) {
      this.token = token;
      localStorage.setItem('token', token);
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    },

    clearToken() {
      this.token = null;
      this.user = null;
      localStorage.removeItem('token');
      delete axios.defaults.headers.common['Authorization'];
    },

    async fetchUser() {
      if (!this.token) return;
      try {
        const response = await axios.get('/api/user');
        this.user = response.data;
      } catch (error) {
        this.clearToken();
      }
    },

    async login(credentials) {
      this.loading = true;
      try {
        const response = await axios.post('/api/login', credentials);
        this.setToken(response.data.token);
        this.user = response.data.user;
        return { success: true };
      } catch (error) {
        return { success: false, message: error.response?.data?.message || 'Login failed' };
      } finally {
        this.loading = false;
      }
    },

    async register(data) {
      this.loading = true;
      try {
        const response = await axios.post('/api/register', data);
        this.setToken(response.data.token);
        this.user = response.data.user;
        return { success: true };
      } catch (error) {
        return { success: false, message: error.response?.data?.message || 'Registration failed' };
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      try {
        await axios.post('/api/logout');
      } finally {
        this.clearToken();
      }
    },
  },
});