import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            registerType: 'autoUpdate',
            // PWA disabled during SSR build
            devOptions: {
                enabled: true,
            },
            manifest: {
                name: 'CommunityERP — Cobrador',
                short_name: 'CommunityERP',
                description: 'App de cobros offline para cobradores',
                theme_color: '#ffffff',
                background_color: '#ffffff',
                display: 'standalone',
                start_url: '/collector',
                icons: [
                    {
                        src: '/icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                ],
            },
            workbox: {
                // Cache static assets
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
                // Network-first for API routes (fresh data when online)
                runtimeCaching: [
                    {
                        urlPattern: /^\/collector\//,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'collector-pages',
                            expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 },
                        },
                    },
                ],
            },
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
});
