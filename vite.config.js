import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin-reach-map.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        tailwindcss(),
        VitePWA({
            // The service worker must live at the site root to control every page,
            // while Vite's assets stay under /build/.
            outDir: 'public',
            base: '/',
            buildBase: '/build/',
            scope: '/',
            registerType: 'autoUpdate',
            injectRegister: null,
            filename: 'sw.js',
            manifestFilename: 'manifest.webmanifest',
            includeAssets: [],
            manifest: {
                name: 'দাওয়াত — পাড়ার ঘরের খাবার',
                short_name: 'দাওয়াত',
                description: 'আশেপাশের গৃহিণীদের আজকের রান্না, কাছেরটা আগে।',
                lang: 'bn',
                id: '/',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                background_color: '#FBF8F1',
                theme_color: '#1E5B3F',
                icons: [
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/icons/icon-512-maskable.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                // Only cache the app shell; API data must always be fresh.
                globDirectory: 'public',
                globPatterns: ['build/assets/*.{js,css,woff2}'],
                navigateFallback: null,
                // F37: notification handlers live in their own small file.
                importScripts: ['/push-sw.js'],
                runtimeCaching: [
                    {
                        urlPattern: ({ url }) => url.pathname.startsWith('/storage/'),
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'dawat-images',
                            expiration: { maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 7 },
                        },
                    },
                ],
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
