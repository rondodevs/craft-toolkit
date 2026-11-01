import { fileURLToPath } from 'node:url'
import vue from '@vitejs/plugin-vue'
import UnoCSS from 'unocss/vite'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { defineConfig } from 'vite'

const app = fileURLToPath(new URL('./app', import.meta.url))

/**
 * One ES module loaded on every CP page by ToolkitAsset: it only mounts the islands, each
 * island is its own chunk. Fixed names, since Craft publishes the folder as it is.
 */
export default defineConfig(({ mode }) => ({
  // Chunks load next to toolkit.js, wherever Craft publishes it (cpresources/<hash>/)
  base: './',
  plugins: [
    vue(),
    UnoCSS(),
    // Imports as in the Nuxt projects: Vue, VueUse and our composables/utils without import lines
    AutoImport({
      imports: ['vue', '@vueuse/core'],
      dirs: [`${app}/composables`, `${app}/utils`],
      dts: `${app}/types/auto-imports.d.ts`,
      vueTemplate: true,
    }),
    Components({
      dirs: [`${app}/components`],
      directoryAsNamespace: false,
      dts: `${app}/types/components.d.ts`,
    }),
  ],
  resolve: {
    alias: { '~': app },
  },
  build: {
    outDir: 'src/web/dist',
    emptyOutDir: true,
    minify: mode !== 'development',
    sourcemap: mode === 'development',
    cssCodeSplit: false,
    modulePreload: false,
    rolldownOptions: {
      input: `${app}/main.ts`,
      output: {
        entryFileNames: 'toolkit.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'toolkit[extname]',
        // Craft loads toolkit.js with `?v=`: a chunk importing anything from it would load a
        // second copy, and mount every island twice. Vite's preload helper gets its own chunk.
        advancedChunks: {
          groups: [{ name: 'preload', test: /preload-helper/ }],
        },
      },
    },
  },
}))
