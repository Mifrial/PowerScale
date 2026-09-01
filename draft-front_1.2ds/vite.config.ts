/// <reference types="vitest" />
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify, { transformAssetUrls } from 'vite-plugin-vuetify';
import { VueMcp } from 'vite-plugin-vue-mcp';
import { resolve } from 'path';

/** GitHub Pages отдаёт проект с /<repo>/; локальный dev остаётся на корне. */
function publicBase(): string {
  const raw = process.env.VITE_BASE ?? '/';
  if (raw === '') return '/';

  return raw.endsWith('/') ? raw : `${raw}/`;
}

export default defineConfig({
  base: publicBase(),
  plugins: [vue({ template: { transformAssetUrls } }), vuetify({ autoImport: true }), VueMcp()],
  server: {
    host: 'powerscale.test.ru',
    port: 3000,
    proxy: {
      '/api': { target: 'http://powerscale.test.ru', changeOrigin: true },
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['src/**/*.{test,spec}.ts', 'eslint/**/*.test.ts'],
  },
});
