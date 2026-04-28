import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from "path";
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true,
    }),
    vue(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
      'radix-vue': '/node_modules/radix-vue',
    },
  },
  optimizeDeps: {
    include: ['chart.js']
  }
});