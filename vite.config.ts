import { defineConfig } from 'vite';
import path from 'path';

/**
 * 비즈뿌리오 메시징 플러그인 프론트엔드 에셋 빌드 설정.
 *
 * 커스텀 핸들러(insertVariable) 를 IIFE 번들로 빌드해 플러그인 활성화 시 로드한다.
 * 출력물은 dist/js/plugin.iife.js 이며 plugin.json assets.js.output 과 일치한다.
 */
export default defineConfig({
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    },

    build: {
        lib: {
            entry: path.resolve(__dirname, 'resources/js/index.ts'),
            name: 'SirsoftMessageBizppurio',
            fileName: 'plugin',
            formats: ['iife'],
        },
        outDir: 'dist',
        emptyOutDir: true,
        sourcemap: true,
        rollupOptions: {
            output: {
                entryFileNames: 'js/plugin.iife.js',
                chunkFileNames: 'js/[name]-[hash].js',
            },
        },
        minify: 'esbuild',
        target: 'es2020',
        chunkSizeWarningLimit: 500,
    },

    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
