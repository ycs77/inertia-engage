import type { Config } from 'tailwindcss'

export default {
  content: [
    './resources/views/app.blade.php',
    './resources/js/components/**/*.vue',
    './resources/js/layouts/**/*.vue',
    './resources/js/pages/**/*.vue',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
} satisfies Config
