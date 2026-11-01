<script setup lang="ts">
// Craft's `_includes/forms/field` markup, so the CP styles it as its own
interface FieldProps {
  label?: string
  id?: string
  instructions?: string
  tip?: string
  warning?: string
  error?: string
}

defineProps<FieldProps>()
</script>

<template>
  <div class="field">
    <div v-if="label" class="heading">
      <label :for="id">{{ label }}</label>
    </div>
    <div v-if="instructions || $slots.instructions" class="instructions">
      <slot name="instructions">
        <p v-html="inlineMarkdown(instructions!)" />
      </slot>
    </div>
    <div class="input ltr">
      <slot />
    </div>
    <p v-if="tip" class="notice has-icon">
      <span class="icon" aria-hidden="true" />
      <span v-html="inlineMarkdown(tip)" />
    </p>
    <p v-if="warning" class="warning has-icon">
      <span class="icon" aria-hidden="true" />
      <span v-html="inlineMarkdown(warning)" />
    </p>
    <ul v-if="error" class="errors">
      <li>{{ error }}</li>
    </ul>
  </div>
</template>
