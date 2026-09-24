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
    // EVERYTHING is bundled, deliberately.
    //
    // framer-motion and Inertia ship browser-targeted ESM, and bundling them is what
    // stops `renderToString` from resolving their browser entry points and crashing.
    // The rest are bundled because the deploy ships ONLY bootstrap/ssr — never
    // node_modules (docs/DEPLOYMENT.md §4). A bundle that still imports "react" by name
    // needs node_modules beside it on the server; without it Node exits instantly with
    // ERR_MODULE_NOT_FOUND, the PHP service stays "running" with no child, and nothing
    // listens on 13714 — which reads as "SSR is down" with no other clue.
    //
    // Self-contained output means the SSR process depends on nothing but the file itself.
    noExternal: true,
  },
})
