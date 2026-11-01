<script setup lang="ts">
interface StatusLineProps {
  message: string
  error?: boolean
}

defineProps<StatusLineProps>()
</script>

<template>
  <div class=":uno: mb-2.5" aria-live="polite">
    Status: <strong :class="{ error }">{{ message }}</strong>
  </div>
</template>
