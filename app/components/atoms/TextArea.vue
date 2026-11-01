<script setup lang="ts">
interface TextAreaProps {
  id?: string
  name?: string
  rows?: number
  placeholder?: string
  code?: boolean
}

withDefaults(defineProps<TextAreaProps>(), {
  rows: 3,
})

const value = defineModel<string | null>()
</script>

<template>
  <textarea
    :id
    v-model="value"
    :name
    :rows
    :placeholder
    class="text fullwidth"
    :class="{ code }"
  />
</template>
