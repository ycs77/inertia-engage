import { URL, fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import Laravel from 'laravel-vite-plugin'
import Vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [
    Laravel({
      input: 'resources/js/app.js',
      refresh: true,
    }),
    Vue({
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
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
})
