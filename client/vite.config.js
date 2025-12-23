import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    allowedHosts: ['.127.0.0.1.nip.io'],
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8001',
        secure: false,
        changeOrigin: false,
      },
    },
  },
})
