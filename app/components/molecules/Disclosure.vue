<script setup lang="ts">
interface DisclosureProps {
  label: string
  badge?: string | null
}

defineProps<DisclosureProps>()

const open = defineModel<boolean>('open', { default: false })
</script>

<template>
  <details class=":uno: disclosure mb-2 border border-hairline rounded-md overflow-hidden" :open @toggle="open = ($event.target as HTMLDetailsElement).open">
    <summary class=":uno: font-600 px-3 py-2 list-none bg-gray-050 flex gap-2 cursor-pointer items-center">
      <span>{{ label }}</span>
      <span v-if="badge" class=":uno: text-2.75 font-400 px-2 py-px rounded-pill bg-gray-200">{{ badge }}</span>
    </summary>
    <div class=":uno: px-3 py-2.5">
      <slot />
    </div>
  </details>
</template>

<style scoped>
summary::-webkit-details-marker {
  display: none;
}

summary::before {
  content: '\25B8';
  transition: transform 0.1s ease;
}

.disclosure[open] > summary::before {
  transform: rotate(90deg);
}
</style>
