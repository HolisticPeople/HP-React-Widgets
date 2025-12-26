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
        // Avoid minified variable names that conflict with WordPress/Elementor globals
        minify: 'terser',
        terserOptions: {
            mangle: {
                // Reserve WordPress and Elementor globals to prevent conflicts
                reserved: ['$e', 'elementorFrontend', 'elementor', 'jQuery', '$', 'wp', 'window'],
            },
            compress: {
                // Don't rename globals
                keep_fnames: true,
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
