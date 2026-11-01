<script setup lang="ts">
interface NoteProps {
  error?: boolean
}

defineProps<NoteProps>()
</script>

<template>
  <div
    class=":uno: text-3 px-3 py-1.5"
    :class="error ? ':uno: text-error border border-error rounded-sm bg-[rgb(207_17_36/6%)]' : ':uno: text-light italic'"
  >
    <slot />
  </div>
</template>
