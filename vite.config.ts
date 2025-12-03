import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "./src"),
        },
    },
    build: {
        // Output to assets/dist so PHP can find it
        outDir: 'assets/dist',
        manifest: true, // Generate manifest.json for PHP to map hashed filenames
        rollupOptions: {
            input: {
                main: path.resolve(__dirname, 'src/main.tsx'),
            },
        },
    },
    server: {
        // Required for HMR in WordPress
        origin: 'http://localhost:5173',
        cors: true,
        strictPort: true,
        port: 5173
    }
})
