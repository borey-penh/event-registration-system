import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => ({
  plugins: [vue()],
  // Embedded build is served by Laravel under /app/, so asset URLs need that base.
  base: mode === 'embedded' ? '/app/' : '/',
  // Candidates open the app from in-app webviews (Messenger/Telegram/Zalo/
  // QR-scanner browsers) on old Android. Default targets ship modern syntax
  // that those webviews cannot parse — the page then stays permanently white
  // with zero feedback. es2015 + terser (real transpilation, not just syntax
  // lowering) keeps the same bundle runnable in them.
  ...(mode === 'embedded' && {
    build: {
      target: 'es2015',
      minify: 'terser',
      terserOptions: {
        // Safari 10 / old iOS webviews choke on some ES2015+ constructs.
        // NB: no `output`/`format` key here — Vite's terser plugin passes
        // its own `format` and terser rejects having both.
        compress: { ecma: 5 },
        mangle: { safari10: true },
      },
    },
  }),
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
