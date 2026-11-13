import axios from 'axios';
import { trackServerEvent } from './pixel';

const TOKEN_KEY = 'dawat.token';

export function getToken() {
    try {
        return localStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

export function setToken(token) {
    try {
        if (token) {
            localStorage.setItem(TOKEN_KEY, token);
        } else {
            localStorage.removeItem(TOKEN_KEY);
        }
    } catch {
        // Private mode or storage blocked: the session simply won't persist.
    }
}

export const api = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
    timeout: 20000,
});

api.interceptors.request.use((config) => {
    const token = getToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// F54: the server says when something worth counting for the ads happened.
api.interceptors.response.use((response) => {
    trackServerEvent(response.data?.meta_event);
    return response;
});

/**
 * Turns an axios error into a message a person can act on.
 * The API sends Bangla messages; fall back to a generic one for network trouble.
 */
export function errorMessage(error, fallback = 'কিছু একটা ঠিকমতো হয়নি। আবার চেষ্টা করুন।') {
    if (!error.response) {
        return 'ইন্টারনেট সংযোগ পাওয়া যাচ্ছে না। নেট চালু আছে কিনা দেখে আবার চেষ্টা করুন।';
    }
    const data = error.response.data || {};
    if (data.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first.length) {
            return first[0];
        }
    }
    return data.message || fallback;
}
