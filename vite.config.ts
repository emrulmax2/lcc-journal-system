import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'

export default defineConfig(({ command }) => ({
  /**
   * The build targets a domain root: https://example.com/ — assets are requested from /.
   * Deploying into a subfolder instead (e.g. example.com/journal/)? Build with:
   *   VITE_BASE=/journal/ npm run build
   * and change RewriteBase in public/.htaccess to match.
   */
  base: command === 'build' ? (process.env.VITE_BASE ?? '/') : '/',
  plugins: [react()],
  resolve: {
    alias: { '@': path.resolve(__dirname, './src') },
  },
  server: { port: 5173, open: true },
}))
