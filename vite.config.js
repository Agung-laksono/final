import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";
import fs from 'fs';
import path from 'path';

// Plugin kustom untuk meng-update versi cache Service Worker saat build
const updateServiceWorkerVersion = () => {
    return {
        name: 'update-sw-version',
        closeBundle() {
            const swPath = path.resolve(__dirname, 'public/sw.js');
            if (fs.existsSync(swPath)) {
                let content = fs.readFileSync(swPath, 'utf-8');
                const timestamp = new Date().getTime();
                // Mencari dan mengganti CACHE_NAME dengan timestamp baru
                content = content.replace(
                    /const CACHE_NAME = 'inventory-pwa-cache-[^']+';/g,
                    `const CACHE_NAME = 'inventory-pwa-cache-v${timestamp}';`
                );
                fs.writeFileSync(swPath, content, 'utf-8');
                console.log(`\n\x1b[32m✓ PWA Service Worker cache version updated to: inventory-pwa-cache-v${timestamp}\x1b[0m\n`);
            }
        }
    };
};

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        updateServiceWorkerVersion(),
    ],
    server: {
        host: '127.0.0.1',
    },
});

// Sync trigger
