import { defineStore } from 'pinia';
import axios from 'axios';

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
    loading: false,
  }),

  getters: {
    itemCount: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),
    total: (state) => state.items.reduce((sum, item) => sum + item.price * item.quantity, 0),
    formattedTotal: (state) => {
      const total = state.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
      return new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(total / 100);
    },
  },

  actions: {
    async fetchCart() {
      try {
        const response = await axios.get('/api/cart');
        this.items = response.data.items || [];
      } catch (error) {
        console.error('Failed to fetch cart:', error);
      }
    },

    async addItem(product, quantity = 1) {
      this.loading = true;
      try {
        const response = await axios.post('/api/cart', { product_id: product.id, quantity });
        this.items = response.data.items || [];
        return { success: true };
      } catch (error) {
        return { success: false, message: error.response?.data?.message || 'Failed to add to cart' };
      } finally {
        this.loading = false;
      }
    },

    async updateQuantity(itemId, quantity) {
      if (quantity < 1) return this.removeItem(itemId);
      try {
        const response = await axios.put(`/api/cart/${itemId}`, { quantity });
        this.items = response.data.items || [];
      } catch (error) {
        console.error('Failed to update quantity:', error);
      }
    },

    async removeItem(itemId) {
      try {
        const response = await axios.delete(`/api/cart/${itemId}`);
        this.items = response.data.items || [];
      } catch (error) {
        console.error('Failed to remove item:', error);
      }
    },

    clear() {
      this.items = [];
    },
  },
});