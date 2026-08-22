/// <reference types="vitest" />
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify, { transformAssetUrls } from 'vite-plugin-vuetify';
import { VueMcp } from 'vite-plugin-vue-mcp';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue({ template: { transformAssetUrls } }), vuetify({ autoImport: true }), VueMcp()],
  server: { port: 3000 },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['src/**/*.{test,spec}.ts'],
  },
});
