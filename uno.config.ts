import type { PresetWind4Theme } from 'unocss'
import { defineConfig, presetWind4, transformerCompileClass, transformerDirectives } from 'unocss'

const wind4 = presetWind4({
  preflights: {
    reset: false,
    // Its :root variables (--spacing, --radius-sm…) would replace Craft's own: see postprocess
    theme: false,
  },
})

/** Craft's CSS variables, so the toolkit follows the CP (and its dark mode) */
const theme = {
  colors: {
    text: 'var(--text-color)',
    light: 'var(--light-text-color)',
    primary: 'var(--primary-color)',
    error: 'var(--error-color)',
    success: 'var(--success-color)',
    hairline: 'var(--hairline-color)',
    white: 'var(--white)',
    gray: {
      '050': 'var(--gray-050)',
      '100': 'var(--gray-100)',
      '200': 'var(--gray-200)',
      '300': 'var(--gray-300)',
      '500': 'var(--gray-500)',
      '600': 'var(--gray-600)',
      '800': 'var(--gray-800)',
    },
  },
  radius: {
    sm: 'var(--radius-sm, 4px)',
    md: 'var(--radius-md, 6px)',
    pill: '20px',
  },
}

/** A theme variable (`fontWeight-bold`) as its value, from our theme or wind4's */
function themeValue(name: string): string | undefined {
  for (const source of [theme, wind4.theme] as Record<string, any>[]) {
    let value: any = source

    for (const key of name.split('-'))
      value = value?.[key]

    value = typeof value === 'object' ? value?.DEFAULT : value

    if (typeof value === 'string')
      return value
  }
}

/**
 * Inside Craft's CP: Craft's own classes (btn, field, pane, lightswitch…) give the native look,
 * UnoCSS only lays out what Craft has no class for. No preflights, so the CP keeps its styles.
 */
export default defineConfig<PresetWind4Theme>({
  presets: [wind4],

  // Only the `:uno:` classes, once compiled to `tk-*`: any other word in the sources (Craft's
  // `flex`, `hidden`, `table`, prose…) would become a global rule over the CP's own classes
  extractorDefault: false,
  extractors: [
    {
      name: 'toolkit-compiled',
      extract: ({ code }) => code.match(/\btk-[a-z0-9]{6}\b/g) ?? [],
    },
  ],

  transformers: [
    transformerDirectives(),
    // `class=":uno: ..."` compiles to one hashed `tk-*` class: no clash with the CP or other plugins
    transformerCompileClass({ classPrefix: 'tk-' }),
  ],

  theme,

  // Utilities get the theme values themselves instead of variables on :root
  postprocess: (util) => {
    for (const entry of util.entries) {
      if (typeof entry[1] === 'string')
        entry[1] = entry[1].replace(/var\(--([\w-]+)\)/g, (match, name: string) => name.startsWith('un-') ? match : themeValue(name) ?? match)
    }
  },

  shortcuts: {
    // Layout utils, as in the frontends
    'lay-v': 'grid grid-flow-row [grid-auto-columns:minmax(0,1fr)] [grid-template-rows:minmax(0,auto)]',
    'lay-h': 'grid grid-flow-col [grid-auto-columns:minmax(0,1fr)] [grid-template-rows:minmax(0,auto)]',
    'lay-o': 'grid [&>*]:[grid-area:1/1]',
  },
})
