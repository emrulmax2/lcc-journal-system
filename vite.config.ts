import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import path from 'node:path'

export default defineConfig({
  plugins: [
    laravel({
      input: 'resources/js/app.tsx',
      ssr: 'resources/js/ssr.tsx',
      refresh: true,
    }),
    react(),
  ],
  resolve: {
    alias: { '@': path.resolve(__dirname, './resources/js') },
  },
  ssr: {
    // framer-motion and Inertia ship browser-targeted ESM. Bundling them into the
    // SSR output (rather than leaving them as bare Node imports) is what stops
    // `renderToString` from resolving their browser entry points and crashing.
    noExternal: ['framer-motion', '@inertiajs/react'],
  },
})
