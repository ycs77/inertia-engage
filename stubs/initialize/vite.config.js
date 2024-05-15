import { defineConfig } from 'vite'
import Laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    Laravel({
      input: 'resources/js/app.js',
      refresh: true,
    }),
  ],
})
