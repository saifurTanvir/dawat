import { defineStore } from 'pinia';
import { api, getToken, setToken } from '@/lib/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: getToken(),
        loaded: false,
    }),

    getters: {
        isSignedIn: (state) => Boolean(state.token && state.user),
    },

    actions: {
        /** Loads the signed-in user once per page load; clears a token the server no longer accepts. */
        async load() {
            if (this.loaded) {
                return;
            }
            this.loaded = true;
            if (!this.token) {
                return;
            }
            try {
                const { data } = await api.get('/me');
                this.user = data.data;
            } catch (error) {
                if (error.response && [401, 403].includes(error.response.status)) {
                    this.forget();
                }
            }
        },

        async requestCode(phone) {
            const { data } = await api.post('/auth/otp', { phone });
            return data;
        },

        async verifyCode(phone, code) {
            const { data } = await api.post('/auth/verify', { phone, code, device_name: 'pwa' });
            this.token = data.token;
            this.user = data.user;
            setToken(data.token);
            return data.user;
        },

        async logout() {
            try {
                await api.post('/auth/logout');
            } finally {
                this.forget();
            }
        },

        forget() {
            this.token = null;
            this.user = null;
            setToken(null);
        },
    },
});
