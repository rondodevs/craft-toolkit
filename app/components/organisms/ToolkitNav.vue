<script setup lang="ts">
export interface NavItem {
  id: string
  label: string
  url: string
  iconSvg: string | null
}

interface ToolkitNavProps {
  items: NavItem[]
  active: string
}

defineProps<ToolkitNavProps>()
</script>

<template>
  <nav class=":uno: mb-6 flex flex-wrap gap-1.5" aria-label="Toolkit sections">
    <a
      v-for="item in items"
      :key="item.id"
      :href="item.url"
      class=":uno: px-3 py-1.5 rounded-pill no-underline inline-flex gap-1.5 items-center"
      :class="item.id === active ? ':uno: text-white bg-primary' : ':uno: text-text bg-gray-050 hover:bg-gray-100'"
      :aria-current="item.id === active ? 'page' : undefined"
    >
      <RawIcon :svg="item.iconSvg" class=":uno: size-3.5" />
      <span>{{ item.label }}</span>
    </a>
  </nav>
</template>
