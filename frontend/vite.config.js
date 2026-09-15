import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    host: true, // listen on LAN IP so candidates can open the app from phones/other PCs
    port: 5173,
    proxy: {
      // Not used by axios (absolute VITE_API_URL), handy for same-origin testing
      '/api': 'http://localhost:8000',
    },
  },
})
