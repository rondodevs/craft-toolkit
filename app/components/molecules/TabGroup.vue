<script setup lang="ts">
export interface Tab {
  id: string
  label: string
}

interface TabGroupProps {
  tabs: Tab[]
  label: string
  small?: boolean
  // Keeps the tab in the URL hash, so a reload reopens it
  hash?: boolean
}

const props = defineProps<TabGroupProps>()

const active = defineModel<string>({ required: true })

onMounted(() => {
  const initial = window.location.hash.slice(1)

  if (props.hash && props.tabs.some(tab => tab.id === initial))
    active.value = initial
})

function select(id: string) {
  active.value = id

  if (props.hash)
    history.replaceState(null, '', `#${id}`)
}
</script>

<template>
  <div class="btngroup" :class="{ small }" role="tablist" :aria-label="label">
    <button
      v-for="tab in tabs"
      :key="tab.id"
      type="button"
      class="btn"
      :class="{ active: tab.id === active, small }"
      role="tab"
      :aria-selected="tab.id === active"
      :tabindex="tab.id === active ? 0 : -1"
      @click="select(tab.id)"
    >
      {{ tab.label }}
    </button>
  </div>
</template>
