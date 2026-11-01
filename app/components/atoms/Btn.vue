<script setup lang="ts">
// Hierarchy: `submit` for the one main action, `default` for the others, `tertiary` (text
// only) for undo/reset-like actions next to them
interface BtnProps {
  variant?: 'default' | 'submit' | 'dashed' | 'tertiary'
  small?: boolean
  add?: boolean
  loading?: boolean
  disabled?: boolean
  type?: 'button' | 'submit'
  href?: string
}

withDefaults(defineProps<BtnProps>(), {
  variant: 'default',
  type: 'button',
})
</script>

<template>
  <a
    v-if="href"
    :href
    class="btn"
    :class="{ submit: variant === 'submit', small }"
    target="_blank"
    rel="noopener noreferrer"
  ><slot /></a>
  <button
    v-else-if="variant === 'tertiary'"
    :type
    class=":uno: text-[var(--link-color)] p-0 border-0 bg-transparent cursor-pointer hover:underline"
    :disabled
  >
    <slot />
  </button>
  <button
    v-else
    :type
    class="btn"
    :class="{
      'submit': variant === 'submit',
      'dashed': variant === 'dashed',
      'small': small,
      'add icon': add,
      'loading': loading,
      'disabled': disabled || loading,
    }"
    :disabled="disabled || loading"
  >
    <div class="label">
      <slot />
    </div>
    <div v-if="loading" class="spinner spinner-absolute" />
  </button>
</template>
