import { defineStore } from 'pinia';
import { api, getToken, setToken } from '@/lib/api';

const ORIGINAL_KEY = 'dawat.original_token';

function readOriginal() {
    try {
        return localStorage.getItem(ORIGINAL_KEY);
    } catch {
        return null;
    }
}

function writeOriginal(value) {
    try {
        if (value === null) localStorage.removeItem(ORIGINAL_KEY);
        else localStorage.setItem(ORIGINAL_KEY, value);
    } catch {
        /* nothing to restore later */
    }
}

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

        /** F50: a one-time login link from the Dawat team. */
        async loginWithLink(linkToken) {
            const { data } = await api.post('/auth/link', { token: linkToken });
            this.token = data.token;
            this.user = data.user;
            this.loaded = true;
            setToken(data.token);
            return data.user;
        },

        /** F51: open the app as someone else; your own sign-in is kept aside. */
        async startImpersonation(code) {
            const { data } = await api.post('/impersonate/claim', { code });
            const own = getToken();
            // Opening a second one from inside the first keeps the real original.
            if (!readOriginal()) writeOriginal(own ?? '');
            this.token = data.token;
            setToken(data.token);
            const me = await api.get('/me');
            this.user = me.data.data;
            this.loaded = true;
            return this.user;
        },

        /** F51: back to your own account (or signed out, when there was none on this device). */
        async endImpersonation() {
            try {
                await api.post('/impersonate/end');
            } catch {
                /* the session may already have ended */
            }
            const original = readOriginal();
            writeOriginal(null);
            this.user = null;
            this.loaded = false;
            this.token = original || null;
            setToken(original || null);
            await this.load();
            return Boolean(original);
        },

        async logout() {
            if (this.user?.impersonation) {
                await this.endImpersonation();
                return;
            }
            try {
                await api.post('/auth/logout');
            } finally {
                this.forget();
            }
        },

        forget() {
            // F51: an expired look into someone else's app falls back to your own account.
            const original = readOriginal();
            writeOriginal(null);
            this.token = original || null;
            this.user = null;
            setToken(original || null);
            if (original) {
                this.loaded = false;
                this.load();
            }
        },
    },
});
