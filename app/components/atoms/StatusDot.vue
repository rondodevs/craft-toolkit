<script setup lang="ts">
export type StatusColor = 'green' | 'orange' | 'red' | 'grey' | 'light' | 'disabled'

interface StatusDotProps {
  color: StatusColor
  label?: string
}

defineProps<StatusDotProps>()
</script>

<template>
  <span class="status" :class="color" :title="label" :aria-label="label" />
</template>
