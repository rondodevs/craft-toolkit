<script setup lang="ts">
interface ChipProps {
  label: string
  selected?: boolean
  removable?: boolean
  title?: string
}

defineProps<ChipProps>()

const emit = defineEmits<{
  click: []
  remove: []
}>()
</script>

<template>
  <span
    class=":uno: text-3 leading-tight px-2 py-0.5 border border-hairline rounded-pill inline-flex gap-1.5 max-w-full items-center"
    :class="selected ? ':uno: text-white border-primary bg-primary' : ':uno: bg-gray-050'"
  >
    <button
      v-if="!removable"
      type="button"
      class=":uno: text-inherit cursor-pointer truncate"
      :title
      @click="emit('click')"
    >{{ label }}</button>
    <span v-else class=":uno: truncate">{{ label }}</span>
    <slot />
    <button
      v-if="removable"
      type="button"
      class=":uno: text-3.5 leading-none opacity-70 cursor-pointer hover:opacity-100"
      :aria-label="`Remove ${label}`"
      @click="emit('remove')"
    >×</button>
  </span>
</template>
