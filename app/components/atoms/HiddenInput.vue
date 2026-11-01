<script setup lang="ts">
interface HiddenInputProps {
  name: string
  value: string | number | null | undefined
}

const props = defineProps<HiddenInputProps>()
const input = useTemplateRef<HTMLInputElement>('input')

// Craft's element editor only sees changes that fire an event: it then saves the draft
// and warns before leaving unsaved
watch(() => props.value, () => {
  nextTick(() => input.value?.dispatchEvent(new Event('change', { bubbles: true })))
})
</script>

<template>
  <input ref="input" type="hidden" :name :value="value ?? ''">
</template>
