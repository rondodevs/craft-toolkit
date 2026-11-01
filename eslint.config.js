import fes from '@studio-fes/eslint-config'

export default fes(
  {
    pnpm: false,
    // PHP plugin: only the CP app is linted
    ignores: ['src/**', 'playground/**', 'vendor/**', '.ddev/**', 'composer.json', 'app/types/*.d.ts'],
  },
  {
    rules: {
      // Craft's own classes (btn, field, light…) sit next to `:uno:` ones and must stay as they are:
      // the rule's fix would compile them too
      'unocss/enforce-class-compile': 'off',
    },
  },
)
