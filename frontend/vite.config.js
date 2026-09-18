import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => ({
  plugins: [vue()],
  // Embedded build is served by Laravel under /app/, so asset URLs need that base.
  base: mode === 'embedded' ? '/app/' : '/',
  server: {
    host: true, // listen on LAN IP so candidates can open the app from phones/other PCs
    port: 5173,
    proxy: {
      // Same-origin API for every device: pages opened from phones via the
      // LAN IP call /api on the Vite server, which forwards to Laravel here.
      '/api': 'http://localhost:8000',
    },
  },
}))
