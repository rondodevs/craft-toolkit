<script setup lang="ts">
interface TextInputProps {
  id?: string
  name?: string
  type?: string
  placeholder?: string
  code?: boolean
  small?: boolean
  min?: number
  max?: number
  step?: number | string
  inputmode?: 'text' | 'decimal' | 'numeric' | 'url' | 'email'
}

withDefaults(defineProps<TextInputProps>(), {
  type: 'text',
})

const value = defineModel<string | number | null>()
</script>

<template>
  <input
    :id
    v-model="value"
    :name
    :type
    :placeholder
    :min
    :max
    :step
    :inputmode
    class="text"
    :class="{ code, fullwidth: !small }"
    autocomplete="off"
  >
</template>
