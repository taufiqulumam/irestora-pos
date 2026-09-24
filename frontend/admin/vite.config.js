import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
            '@shared': resolve(__dirname, '../shared'),
        },
    },
    server: {
        port: 3002,
        host: true,
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
        },
    },
    build: {
        target: 'es2022',
        minify: 'esbuild',
        sourcemap: true,
        rollupOptions: {
            output: {
                manualChunks: (id) => {
                    if (id.includes('node_modules')) {
                        if (id.includes('primevue') || id.includes('@primevue')) {
                            return 'primevue';
                        }
                        if (id.includes('chart.js')) {
                            return 'charts';
                        }
                        return 'vendor';
                    }
                },
            },
        },
    },
});
