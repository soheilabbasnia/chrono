import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  base: './',
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api.php': 'http://127.0.0.1:8099'
    }
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true
  }
});
