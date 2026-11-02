import { defineConfig } from 'vite';
import path from 'path';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            ziggy: path.resolve(__dirname, 'vendor/tightenco/ziggy/dist/ziggy.js'),
        },
    },
    build: {
        outDir: 'public/build',
    },
    base: '/projects/blackpool/',  // Asegúrate de usar el subdirectorio adecuado
});
